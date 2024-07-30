const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const cleanCSS = require('gulp-clean-css');
const uglify = require('gulp-uglify');
const concat = require('gulp-concat');
const rename = require('gulp-rename');

// File paths
const paths = {
  scss: {
    src: 'scss/**/*.scss',
    dest: 'dest/css/'
  },
  js: {
    src: 'js/**/*.js',
    dest: 'dest/js/'
  }
};

// Task to compile SCSS to CSS and minify CSS
function styles() {
  return gulp.src(paths.scss.src)
    .pipe(sass().on('error', sass.logError))
    .pipe(gulp.dest(paths.scss.dest))
    .pipe(cleanCSS())
    .pipe(rename({ suffix: '.min' }))
    .pipe(gulp.dest(paths.scss.dest));
}

// Task to concatenate and minify JS files
function scripts() {
  return gulp.src(paths.js.src)
    .pipe(concat('all.js'))
    .pipe(gulp.dest(paths.js.dest))
    .pipe(uglify())
    .pipe(rename({ suffix: '.min' }))
    .pipe(gulp.dest(paths.js.dest));
}

// Watch for changes in SCSS and JS files
function watch() {
  gulp.watch(paths.scss.src, styles);
  gulp.watch(paths.js.src, scripts);
}

// Export tasks for command line usage
exports.styles = styles;
exports.scripts = scripts;
exports.watch = watch;

// Default task
exports.default = gulp.series(styles, scripts, watch);
