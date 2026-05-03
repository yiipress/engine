#ifdef HAVE_CONFIG_H
# include "config.h"
#endif

#include "php.h"
#include "zend_exceptions.h"
#include "ext/spl/spl_exceptions.h"
#include "php_yiipress_highlighter.h"

extern char *yiipress_highlighter_highlight(
    const char *html,
    size_t html_len,
    const char *theme_name,
    size_t theme_name_len,
    size_t *result_len,
    const char **error
);

extern void yiipress_highlighter_free(char *ptr);

PHP_FUNCTION(yiipress_highlight_html);

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_yiipress_highlight_html, 0, 1, IS_STRING, 1)
    ZEND_ARG_TYPE_INFO(0, html, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, themeName, IS_STRING, 0, "\"\"")
ZEND_END_ARG_INFO()

static const zend_function_entry yiipress_highlighter_functions[] = {
    PHP_FE(yiipress_highlight_html, arginfo_yiipress_highlight_html)
    PHP_FE_END
};

zend_module_entry yiipress_highlighter_module_entry = {
    STANDARD_MODULE_HEADER,
    "yiipress_highlighter",
    yiipress_highlighter_functions,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    PHP_YIIPRESS_HIGHLIGHTER_VERSION,
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_YIIPRESS_HIGHLIGHTER
# ifdef ZTS
ZEND_TSRMLS_CACHE_DEFINE()
# endif
ZEND_GET_MODULE(yiipress_highlighter)
#endif

PHP_FUNCTION(yiipress_highlight_html)
{
    zend_string *html;
    zend_string *theme_name = NULL;
    size_t result_len = 0;
    const char *error = NULL;
    char *result;
    zend_string *return_string;

    ZEND_PARSE_PARAMETERS_START(1, 2)
        Z_PARAM_STR(html)
        Z_PARAM_OPTIONAL
        Z_PARAM_STR(theme_name)
    ZEND_PARSE_PARAMETERS_END();

    result = yiipress_highlighter_highlight(
        ZSTR_VAL(html),
        ZSTR_LEN(html),
        theme_name == NULL ? NULL : ZSTR_VAL(theme_name),
        theme_name == NULL ? 0 : ZSTR_LEN(theme_name),
        &result_len,
        &error
    );

    if (result == NULL) {
        if (error == NULL) {
            RETURN_NULL();
        }

        zend_throw_exception(spl_ce_RuntimeException, error, 0);
        yiipress_highlighter_free((char *) error);
        RETURN_THROWS();
    }

    return_string = zend_string_init(result, result_len, 0);
    yiipress_highlighter_free(result);
    RETURN_STR(return_string);
}
