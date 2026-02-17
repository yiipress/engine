use rayon::prelude::*;
use std::ffi::{CStr, CString};
use std::os::raw::c_char;
use syntect::highlighting::ThemeSet;
use syntect::html::highlighted_html_for_string;
use syntect::parsing::SyntaxSet;

struct Block {
    start: usize,
    end: usize,
    language: String,
    code: String,
}

fn find_code_blocks(html: &str) -> Vec<Block> {
    let mut blocks = Vec::new();
    let mut search_from = 0;

    while let Some(pre_start) = html[search_from..].find("<pre><code") {
        let abs_start = search_from + pre_start;
        let after_code_tag = &html[abs_start + 10..];

        let language = if after_code_tag.starts_with(" class=\"language-") {
            let lang_start = 17; // length of ` class="language-`
            if let Some(quote_end) = after_code_tag[lang_start..].find('"') {
                after_code_tag[lang_start..lang_start + quote_end].to_string()
            } else {
                search_from = abs_start + 10;
                continue;
            }
        } else {
            String::new()
        };

        let tag_close = match html[abs_start..].find('>') {
            Some(pos) => abs_start + pos + 1,
            None => {
                search_from = abs_start + 10;
                continue;
            }
        };

        // Find the inner > of <code...>
        let code_content_start = match html[tag_close..].find('>') {
            Some(pos) => tag_close + pos + 1,
            None => {
                search_from = abs_start + 10;
                continue;
            }
        };

        let end_marker = "</code></pre>";
        let code_content_end = match html[code_content_start..].find(end_marker) {
            Some(pos) => code_content_start + pos,
            None => {
                search_from = abs_start + 10;
                continue;
            }
        };

        let block_end = code_content_end + end_marker.len();

        let code = html[code_content_start..code_content_end].to_string();

        blocks.push(Block {
            start: abs_start,
            end: block_end,
            language,
            code,
        });

        search_from = block_end;
    }

    blocks
}

fn decode_html_entities(s: &str) -> String {
    s.replace("&amp;", "&")
        .replace("&lt;", "<")
        .replace("&gt;", ">")
        .replace("&quot;", "\"")
        .replace("&#39;", "'")
        .replace("&#x27;", "'")
}

fn highlight_block(ss: &SyntaxSet, theme: &syntect::highlighting::Theme, block: &Block) -> String {
    if block.language.is_empty() {
        return String::new();
    }

    let syntax = ss
        .find_syntax_by_token(&block.language)
        .unwrap_or_else(|| ss.find_syntax_plain_text());

    let decoded = decode_html_entities(&block.code);

    match highlighted_html_for_string(&decoded, ss, syntax, theme) {
        Ok(highlighted) => highlighted,
        Err(_) => String::new(),
    }
}

/// Highlights all `<pre><code class="language-xxx">` blocks in the given HTML string.
///
/// # Safety
///
/// `html_ptr` must be a valid null-terminated UTF-8 C string.
/// The returned pointer must be freed by calling `yiipress_highlight_free`.
#[no_mangle]
pub unsafe extern "C" fn yiipress_highlight(html_ptr: *const c_char) -> *mut c_char {
    if html_ptr.is_null() {
        return std::ptr::null_mut();
    }

    let html = match unsafe { CStr::from_ptr(html_ptr) }.to_str() {
        Ok(s) => s,
        Err(_) => return std::ptr::null_mut(),
    };

    let blocks = find_code_blocks(html);
    if blocks.is_empty() {
        return match CString::new(html) {
            Ok(c) => c.into_raw(),
            Err(_) => std::ptr::null_mut(),
        };
    }

    let ss = SyntaxSet::load_defaults_newlines();
    let ts = ThemeSet::load_defaults();
    let theme = &ts.themes["InspiredGitHub"];

    let highlighted: Vec<String> = blocks
        .par_iter()
        .map(|block| highlight_block(&ss, theme, block))
        .collect();

    let mut result = String::with_capacity(html.len());
    let mut last_end = 0;

    for (block, replacement) in blocks.iter().zip(highlighted.iter()) {
        result.push_str(&html[last_end..block.start]);
        if replacement.is_empty() {
            result.push_str(&html[block.start..block.end]);
        } else {
            result.push_str(replacement);
        }
        last_end = block.end;
    }
    result.push_str(&html[last_end..]);

    match CString::new(result) {
        Ok(c) => c.into_raw(),
        Err(_) => std::ptr::null_mut(),
    }
}

/// Frees a string previously returned by `yiipress_highlight`.
///
/// # Safety
///
/// `ptr` must be a pointer returned by `yiipress_highlight`, or null.
#[no_mangle]
pub unsafe extern "C" fn yiipress_highlight_free(ptr: *mut c_char) {
    if !ptr.is_null() {
        unsafe {
            drop(CString::from_raw(ptr));
        }
    }
}
