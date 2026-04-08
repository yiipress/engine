use std::borrow::Cow;
use std::ffi::CString;
use std::os::raw::c_char;
use std::ptr;
use std::slice;
use std::str;
use std::sync::LazyLock;

use rayon::prelude::*;
use syntect::highlighting::{Theme, ThemeSet};
use syntect::html::highlighted_html_for_string;
use syntect::parsing::SyntaxSet;

const HIGHLIGHTABLE_BLOCK_MARKER: &str = "<pre><code class=\"language-";
const CODE_BLOCK_END_MARKER: &str = "</code></pre>";
const PARALLEL_BLOCK_THRESHOLD: usize = 8;

struct Block<'a> {
    start: usize,
    end: usize,
    language: &'a str,
    code: &'a str,
}

static SYNTAX_SET: LazyLock<SyntaxSet> = LazyLock::new(SyntaxSet::load_defaults_newlines);
static THEME_SET: LazyLock<ThemeSet> = LazyLock::new(ThemeSet::load_defaults);
const DEFAULT_THEME_NAME: &str = "InspiredGitHub";

fn find_code_blocks(html: &str) -> Vec<Block<'_>> {
    if !html.contains(HIGHLIGHTABLE_BLOCK_MARKER) {
        return Vec::new();
    }

    let mut blocks = Vec::new();
    let mut search_from = 0;

    while let Some(block_start) = html[search_from..].find(HIGHLIGHTABLE_BLOCK_MARKER) {
        let start = search_from + block_start;
        let language_start = start + HIGHLIGHTABLE_BLOCK_MARKER.len();

        let language_end = match html[language_start..].find('"') {
            Some(pos) => language_start + pos,
            None => {
                search_from = language_start;
                continue;
            }
        };

        let code_tag_end = match html[language_end..].find('>') {
            Some(pos) => language_end + pos,
            None => {
                search_from = language_end;
                continue;
            }
        };

        let code_content_start = code_tag_end + 1;
        let code_content_end = match html[code_content_start..].find(CODE_BLOCK_END_MARKER) {
            Some(pos) => code_content_start + pos,
            None => {
                search_from = code_content_start;
                continue;
            }
        };

        let block_end = code_content_end + CODE_BLOCK_END_MARKER.len();

        blocks.push(Block {
            start,
            end: block_end,
            language: &html[language_start..language_end],
            code: &html[code_content_start..code_content_end],
        });

        search_from = block_end;
    }

    blocks
}

fn decode_html_entities(s: &str) -> Cow<'_, str> {
    if !s.contains('&') {
        return Cow::Borrowed(s);
    }

    let mut result = String::with_capacity(s.len());
    let mut cursor = 0;

    while let Some(offset) = s[cursor..].find('&') {
        let entity_start = cursor + offset;
        result.push_str(&s[cursor..entity_start]);

        let remaining = &s[entity_start..];
        if remaining.starts_with("&amp;") {
            result.push('&');
            cursor = entity_start + 5;
        } else if remaining.starts_with("&lt;") {
            result.push('<');
            cursor = entity_start + 4;
        } else if remaining.starts_with("&gt;") {
            result.push('>');
            cursor = entity_start + 4;
        } else if remaining.starts_with("&quot;") {
            result.push('"');
            cursor = entity_start + 6;
        } else if remaining.starts_with("&#39;") || remaining.starts_with("&#x27;") {
            result.push('\'');
            cursor = entity_start + 5 + usize::from(remaining.starts_with("&#x27;"));
        } else {
            result.push('&');
            cursor = entity_start + 1;
        }
    }

    result.push_str(&s[cursor..]);
    Cow::Owned(result)
}

fn strip_leading_php_open_tag(html: &str) -> Cow<'_, str> {
    if let Some(idx) = html.find("&lt;?php") {
        // Remove the escaped opening tag and following plain-space/newline if present
        let mut end = idx + "&lt;?php".len();
        let bytes = html.as_bytes();
        while end < bytes.len() && (bytes[end] == b' ' || bytes[end] == b'\n' || bytes[end] == b'\r' || bytes[end] == b'\t') {
            end += 1;
        }
        let mut result = String::with_capacity(html.len() - (end - idx));
        result.push_str(&html[..idx]);
        result.push_str(&html[end..]);
        return Cow::Owned(result);
    }
    Cow::Borrowed(html)
}

fn highlight_block(ss: &SyntaxSet, theme: &Theme, block: &Block<'_>) -> Option<String> {
    let syntax = ss
        .find_syntax_by_token(&block.language)
        .unwrap_or_else(|| ss.find_syntax_plain_text());

    let decoded = decode_html_entities(&block.code);

    let is_php = block.language.eq_ignore_ascii_case("php");
    let needs_php_tag = is_php && !decoded.trim_start().starts_with("<?");

    let source: Cow<'_, str> = if needs_php_tag {
        let mut s = String::with_capacity(7 + decoded.len());
        s.push_str("<?php\n");
        s.push_str(&decoded);
        Cow::Owned(s)
    } else {
        decoded
    };

    let out = highlighted_html_for_string(&source, ss, syntax, theme).ok()?;

    if needs_php_tag {
        Some(strip_leading_php_open_tag(&out).into_owned())
    } else {
        Some(out)
    }
}

fn merge_replacements(
    html: &str,
    blocks: &[Block<'_>],
    replacements: &[Option<String>],
) -> String {
    let mut result = String::with_capacity(html.len() + html.len() / 4);
    let mut last_end = 0;

    for (block, replacement) in blocks.iter().zip(replacements.iter()) {
        result.push_str(&html[last_end..block.start]);

        if let Some(replacement) = replacement {
            result.push_str(replacement);
        } else {
            result.push_str(&html[block.start..block.end]);
        }

        last_end = block.end;
    }

    result.push_str(&html[last_end..]);
    result
}

fn highlight_blocks_sequential(
    html: &str,
    blocks: &[Block<'_>],
    ss: &SyntaxSet,
    theme: &Theme,
) -> Option<String> {
    let mut result = String::with_capacity(html.len() + html.len() / 4);
    let mut last_end = 0;
    let mut has_replacements = false;

    for block in blocks {
        result.push_str(&html[last_end..block.start]);

        if let Some(replacement) = highlight_block(ss, theme, block) {
            result.push_str(&replacement);
            has_replacements = true;
        } else {
            result.push_str(&html[block.start..block.end]);
        }

        last_end = block.end;
    }

    result.push_str(&html[last_end..]);

    has_replacements.then_some(result)
}

fn into_raw_c_string_unchecked(value: String) -> *mut c_char {
    // HTML produced by YiiPress is text-only. Interior NUL bytes are unsupported at this FFI boundary.
    unsafe { CString::from_vec_unchecked(value.into_bytes()).into_raw() }
}

fn str_to_raw_c_string_unchecked(value: &str) -> *mut c_char {
    unsafe { CString::from_vec_unchecked(value.as_bytes().to_vec()).into_raw() }
}

fn set_error(error_ptr: *mut *const c_char, message: &str) {
    if error_ptr.is_null() {
        return;
    }

    let error_msg = str_to_raw_c_string_unchecked(message);

    unsafe {
        *error_ptr = error_msg.cast_const();
    }
}

/// Highlights all `<pre><code class="language-xxx">` blocks in the given HTML string.
///
/// # Safety
///
/// `html_ptr` must point to `html_len` bytes of valid UTF-8 data.
/// `result_len_ptr` must be a valid pointer to a `usize` that will be set to the result length
/// (or zero if no replacement was produced).
/// `error_ptr` must be a valid pointer to a `*const c_char` that will be set to an error message
/// (or null if successful). The error message must be freed by calling `yiipress_highlight_free`.
/// The returned pointer must be freed by calling `yiipress_highlight_free`.
#[no_mangle]
pub unsafe extern "C" fn yiipress_highlight(
    html_ptr: *const c_char,
    html_len: usize,
    theme_name_ptr: *const c_char,
    theme_name_len: usize,
    result_len_ptr: *mut usize,
    error_ptr: *mut *const c_char,
) -> *mut c_char {
    if !error_ptr.is_null() {
        unsafe {
            *error_ptr = ptr::null();
        }
    }

    if !result_len_ptr.is_null() {
        unsafe {
            *result_len_ptr = 0;
        }
    }

    if html_ptr.is_null() {
        set_error(error_ptr, "HTML input pointer is null");
        return ptr::null_mut();
    }

    if result_len_ptr.is_null() {
        set_error(error_ptr, "Result length pointer is null");
        return ptr::null_mut();
    }

    let html_bytes = unsafe { slice::from_raw_parts(html_ptr.cast::<u8>(), html_len) };
    let html = match str::from_utf8(html_bytes) {
        Ok(s) => s,
        Err(e) => {
            set_error(error_ptr, &format!("Invalid UTF-8 in HTML input: {}", e));
            return ptr::null_mut();
        }
    };

    let requested_theme_name = if theme_name_ptr.is_null() || theme_name_len == 0 {
        DEFAULT_THEME_NAME
    } else {
        let theme_name_bytes = unsafe { slice::from_raw_parts(theme_name_ptr.cast::<u8>(), theme_name_len) };
        match str::from_utf8(theme_name_bytes) {
            Ok(s) if !s.is_empty() => s,
            Ok(_) => DEFAULT_THEME_NAME,
            Err(e) => {
                set_error(error_ptr, &format!("Invalid UTF-8 in highlight theme name: {}", e));
                return ptr::null_mut();
            }
        }
    };

    let blocks = find_code_blocks(html);
    if blocks.is_empty() {
        // Return null to signal "no changes needed" — PHP should use the original string.
        return ptr::null_mut();
    }

    let ss = &*SYNTAX_SET;
    let theme = match THEME_SET.themes.get(requested_theme_name) {
        Some(theme) => theme,
        None => {
            let mut available_themes: Vec<&str> = THEME_SET.themes.keys().map(String::as_str).collect();
            available_themes.sort_unstable();
            set_error(
                error_ptr,
                &format!(
                    "Unknown highlight theme \"{}\". Available themes: {}",
                    requested_theme_name,
                    available_themes.join(", ")
                ),
            );
            return ptr::null_mut();
        }
    };

    let result = if blocks.len() < PARALLEL_BLOCK_THRESHOLD {
        match highlight_blocks_sequential(html, &blocks, ss, theme) {
            Some(result) => result,
            None => return ptr::null_mut(),
        }
    } else {
        // Rayon thread dispatch has overhead; only parallelize larger pages with many code blocks.
        let replacements: Vec<Option<String>> = blocks
            .par_iter()
            .map(|block| highlight_block(ss, theme, block))
            .collect();

        if replacements.iter().all(Option::is_none) {
            return ptr::null_mut();
        }

        merge_replacements(html, &blocks, &replacements)
    };

    unsafe {
        *result_len_ptr = result.len();
    }

    into_raw_c_string_unchecked(result)
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
