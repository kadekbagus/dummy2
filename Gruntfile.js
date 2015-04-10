module.exports = function(grunt) {
  require('jit-grunt')(grunt);
  
  grunt.initConfig({
    less: {
      development: {
        options: {
          compress: true,
          yuicompress: true,
          optimization: 2
        },
        files: {
          "./public/mobile-ci/stylesheet/main.css": "./public/mobile-ci/styles-less/main.less"
        }
      }
    },
    watch: {
      styles: {
        options: {
          nospawn: true
          // spawn: false,
          // event: ["added", "deleted", "changed"]
          },
        files: ["./public/mobile-ci/styles-less/**/*.less", "./public/mobile-ci/stylesheet/**/*.css"],
        tasks: ["less"]
      }
    },
    copy: {
      // files: {
      // // cwd: "./public/mobile-ci/styles-less/fontawesome/fonts",
      // cwd: "./public/mobile-ci/styles-less/",
      // src: ["fontawesome/fonts/**/*", "bootstrap/fonts/**/*"],
      // dest: "./public/mobile-ci/fonts/",
      // expand: true
    // },
      fontfa: {
        cwd: "./public/mobile-ci/styles-less/fontawesome/fonts",
        src: "**/*",
        dest: "./public/mobile-ci/fonts/",
        expand: true
      },
      fontbootstrap: {
        cwd: "./public/mobile-ci/styles-less/bootstrap/fonts",
        src: "**/*",
        dest: "./public/mobile-ci/fonts/",
        expand: true
      }
    }
});

  grunt.loadNpmTasks("grunt-contrib-less");
  grunt.loadNpmTasks("grunt-contrib-watch");
  grunt.loadNpmTasks("grunt-contrib-copy");

  grunt.registerTask("default", ["less", "copy:fontfa", "copy:fontbootstrap", "watch:styles"]);
};
