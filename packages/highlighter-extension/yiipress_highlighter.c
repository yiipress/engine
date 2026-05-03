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

extern char *yiipress_highlighter_highlight_code(
    const char *code,
    size_t code_len,
    const char *language,
    size_t language_len,
    const char *theme_name,
    size_t theme_name_len,
    size_t *result_len,
    const char **error
);

extern void yiipress_highlighter_free(char *ptr);

static zend_class_entry *yiipress_highlighter_ce;

ZEND_BEGIN_ARG_INFO_EX(arginfo_yiipress_highlighter_construct, 0, 0, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, defaultTheme, IS_STRING, 0, "\"\"")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_yiipress_highlighter_highlight_html, 0, 1, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, html, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, themeName, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_yiipress_highlighter_highlight, 0, 2, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, code, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO(0, language, IS_STRING, 0)
    ZEND_ARG_TYPE_INFO_WITH_DEFAULT_VALUE(0, themeName, IS_STRING, 1, "null")
ZEND_END_ARG_INFO()

PHP_METHOD(YiiPress_Highlighter, __construct);
PHP_METHOD(YiiPress_Highlighter, highlightHtml);
PHP_METHOD(YiiPress_Highlighter, highlight);

static const zend_function_entry yiipress_highlighter_methods[] = {
    PHP_ME(YiiPress_Highlighter, __construct, arginfo_yiipress_highlighter_construct, ZEND_ACC_PUBLIC)
    PHP_ME(YiiPress_Highlighter, highlightHtml, arginfo_yiipress_highlighter_highlight_html, ZEND_ACC_PUBLIC)
    PHP_ME(YiiPress_Highlighter, highlight, arginfo_yiipress_highlighter_highlight, ZEND_ACC_PUBLIC)
    PHP_FE_END
};

PHP_MINIT_FUNCTION(yiipress_highlighter)
{
    zend_class_entry ce;

    INIT_NS_CLASS_ENTRY(ce, "YiiPress", "Highlighter", yiipress_highlighter_methods);
    yiipress_highlighter_ce = zend_register_internal_class(&ce);
    yiipress_highlighter_ce->ce_flags |= ZEND_ACC_FINAL;

    zend_declare_property_string(
        yiipress_highlighter_ce,
        "defaultTheme",
        sizeof("defaultTheme") - 1,
        "",
        ZEND_ACC_PRIVATE
    );

    return SUCCESS;
}

zend_module_entry yiipress_highlighter_module_entry = {
    STANDARD_MODULE_HEADER,
    "yiipress_highlighter",
    NULL,
    PHP_MINIT(yiipress_highlighter),
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

PHP_METHOD(YiiPress_Highlighter, __construct)
{
    zend_string *default_theme;

    ZEND_PARSE_PARAMETERS_START(0, 1)
        Z_PARAM_OPTIONAL
        Z_PARAM_STR(default_theme)
    ZEND_PARSE_PARAMETERS_END();

    if (ZEND_NUM_ARGS() == 0) {
        return;
    }

    zend_update_property_str(
        yiipress_highlighter_ce,
        Z_OBJ_P(ZEND_THIS),
        "defaultTheme",
        sizeof("defaultTheme") - 1,
        default_theme
    );
}

static zend_string *yiipress_highlighter_resolve_theme_name(zend_object *object, zend_string *theme_name)
{
    zval *default_theme;

    if (theme_name != NULL) {
        return theme_name;
    }

    default_theme = zend_read_property(
        yiipress_highlighter_ce,
        object,
        "defaultTheme",
        sizeof("defaultTheme") - 1,
        0,
        NULL
    );

    if (Z_TYPE_P(default_theme) == IS_STRING) {
        return Z_STR_P(default_theme);
    }

    return NULL;
}

PHP_METHOD(YiiPress_Highlighter, highlightHtml)
{
    zend_string *html;
    zend_string *theme_name = NULL;
    zend_string *resolved_theme_name;
    size_t result_len = 0;
    const char *error = NULL;
    char *result;
    zend_string *return_string;

    ZEND_PARSE_PARAMETERS_START(1, 2)
        Z_PARAM_STR(html)
        Z_PARAM_OPTIONAL
        Z_PARAM_STR_OR_NULL(theme_name)
    ZEND_PARSE_PARAMETERS_END();

    resolved_theme_name = yiipress_highlighter_resolve_theme_name(Z_OBJ_P(ZEND_THIS), theme_name);

    result = yiipress_highlighter_highlight(
        ZSTR_VAL(html),
        ZSTR_LEN(html),
        resolved_theme_name == NULL ? NULL : ZSTR_VAL(resolved_theme_name),
        resolved_theme_name == NULL ? 0 : ZSTR_LEN(resolved_theme_name),
        &result_len,
        &error
    );

    if (result == NULL) {
        if (error == NULL) {
            RETURN_STR_COPY(html);
        }

        zend_throw_exception(spl_ce_RuntimeException, error, 0);
        yiipress_highlighter_free((char *) error);
        RETURN_THROWS();
    }

    return_string = zend_string_init(result, result_len, 0);
    yiipress_highlighter_free(result);
    RETURN_STR(return_string);
}

PHP_METHOD(YiiPress_Highlighter, highlight)
{
    zend_string *code;
    zend_string *language;
    zend_string *theme_name = NULL;
    zend_string *resolved_theme_name;
    size_t result_len = 0;
    const char *error = NULL;
    char *result;
    zend_string *return_string;

    ZEND_PARSE_PARAMETERS_START(2, 3)
        Z_PARAM_STR(code)
        Z_PARAM_STR(language)
        Z_PARAM_OPTIONAL
        Z_PARAM_STR_OR_NULL(theme_name)
    ZEND_PARSE_PARAMETERS_END();

    resolved_theme_name = yiipress_highlighter_resolve_theme_name(Z_OBJ_P(ZEND_THIS), theme_name);

    result = yiipress_highlighter_highlight_code(
        ZSTR_VAL(code),
        ZSTR_LEN(code),
        ZSTR_VAL(language),
        ZSTR_LEN(language),
        resolved_theme_name == NULL ? NULL : ZSTR_VAL(resolved_theme_name),
        resolved_theme_name == NULL ? 0 : ZSTR_LEN(resolved_theme_name),
        &result_len,
        &error
    );

    if (result == NULL) {
        if (error == NULL) {
            RETURN_STR_COPY(code);
        }

        zend_throw_exception(spl_ce_RuntimeException, error, 0);
        yiipress_highlighter_free((char *) error);
        RETURN_THROWS();
    }

    return_string = zend_string_init(result, result_len, 0);
    yiipress_highlighter_free(result);
    RETURN_STR(return_string);
}
