PHP_ARG_ENABLE([yiipress-highlighter],
  [for YiiPress highlighter support],
  [AS_HELP_STRING([--enable-yiipress-highlighter],
    [Build YiiPress highlighter extension])],
  [yes])

if test "$PHP_YIIPRESS_HIGHLIGHTER" != "no"; then
  AC_PATH_PROG([CARGO], [cargo], [no])
  if test "$CARGO" = "no"; then
    AC_MSG_ERROR([cargo is required to build ext-yiipress_highlighter])
  fi

  YIIPRESS_HIGHLIGHTER_DIR="$YIIPRESS_HIGHLIGHTER_SOURCE"
  if test -z "$YIIPRESS_HIGHLIGHTER_DIR" && test -f "$ext_srcdir/Cargo.toml"; then
    YIIPRESS_HIGHLIGHTER_DIR="$ext_srcdir"
  fi
  if test -z "$YIIPRESS_HIGHLIGHTER_DIR" || test ! -f "$YIIPRESS_HIGHLIGHTER_DIR/Cargo.toml"; then
    YIIPRESS_HIGHLIGHTER_DIR="$srcdir"
  fi
  YIIPRESS_HIGHLIGHTER_TARGET_DIR="$YIIPRESS_HIGHLIGHTER_DIR/target/release"
  YIIPRESS_HIGHLIGHTER_CARGO_ARGS="build --release"
  if test -n "$CARGO_BUILD_TARGET"; then
    YIIPRESS_HIGHLIGHTER_CARGO_ARGS="$YIIPRESS_HIGHLIGHTER_CARGO_ARGS --target $CARGO_BUILD_TARGET"
    YIIPRESS_HIGHLIGHTER_TARGET_DIR="$YIIPRESS_HIGHLIGHTER_DIR/target/$CARGO_BUILD_TARGET/release"
  fi

  AC_MSG_NOTICE([building Rust highlighter static library])
  (cd "$YIIPRESS_HIGHLIGHTER_DIR" && "$CARGO" $YIIPRESS_HIGHLIGHTER_CARGO_ARGS) || AC_MSG_ERROR([failed to build Rust highlighter static library])

  PHP_ADD_INCLUDE([$YIIPRESS_HIGHLIGHTER_DIR])
  if test "$ext_shared" = "yes"; then
    PHP_ADD_LIBRARY_WITH_PATH([yiipress_highlighter], [$YIIPRESS_HIGHLIGHTER_TARGET_DIR], [YIIPRESS_HIGHLIGHTER_SHARED_LIBADD])
    PHP_ADD_LIBRARY([pthread], [1], [YIIPRESS_HIGHLIGHTER_SHARED_LIBADD])
    PHP_ADD_LIBRARY([dl], [1], [YIIPRESS_HIGHLIGHTER_SHARED_LIBADD])
    PHP_ADD_LIBRARY([m], [1], [YIIPRESS_HIGHLIGHTER_SHARED_LIBADD])
    PHP_SUBST([YIIPRESS_HIGHLIGHTER_SHARED_LIBADD])
  else
    PHP_ADD_LIBRARY_WITH_PATH([yiipress_highlighter], [$YIIPRESS_HIGHLIGHTER_TARGET_DIR])
    PHP_ADD_LIBRARY([pthread])
    PHP_ADD_LIBRARY([dl])
    PHP_ADD_LIBRARY([m])
  fi
  PHP_NEW_EXTENSION([yiipress_highlighter], [yiipress_highlighter.c], [$ext_shared])
fi
