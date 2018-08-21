module.exports = function(grunt) {
    const es2015Preset = require('babel-preset-es2015');
    const reactPreset = require('babel-preset-react');
    const babelstage2 = require('babel-preset-stage-2');
    const sass = require('node-sass');

    /*var es2015Preset = require('babel-preset-es2015');*/
    /*var reactPreset = require('babel-preset-react');*/

    grunt.initConfig( {
        watch: {
            components: {
                files: [
                    'src/*.js',
                    'src/**/*.js'
                ],
                tasks: ['browserify:components'],
                options: {
                    interrupt: true,
                    livereload : true
                }
            },
            css: {
                files: [
                    'src/**/*.scss',
                    'assets/**/*.scss'
                ],
                tasks: ['sass','autoprefixer'],
                options: {
                    livereload : true
                }
            }
        },
        browserify: {
            components: {
                options: {
                    transform: [
                        [ 'babelify', { presets: [ es2015Preset, reactPreset,babelstage2 ] } ]
                    ],
                    browserifyOptions: {
                        paths: [ __dirname + '/node_modules' ]
                    }
                },
                src: [
                    'src/*.js',
                    'src/**/*.js'
                ],
                dest:  '../static/build/js/main.js'
            },
        },
        autoprefixer:{
            options: {
                browsers: ['last 2 versions']
            },
            dist:{
                files:{
                    '../static/build/css/style.css':'../static/build/css/style.css'
                }
            }
        },
        sass: {
            options: {
                sourceMap: false,
                implementation: sass,
                includePaths: ['src','assets']
            },
            dist: {
                src: [
                    'src/Main.scss'
                ],
                dest: '../static/build/css/style.css'
            },
        }
    });

    // Define your tasks here
    grunt.registerTask('default', ['bundle:js','sass','autoprefixer']);

    grunt.registerTask('bundle:js', [
        'browserify:components'
    ]);

    grunt.loadNpmTasks('grunt-browserify');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-autoprefixer');
    grunt.loadNpmTasks('grunt-sass');

};

