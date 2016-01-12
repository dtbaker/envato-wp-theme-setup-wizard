module.exports = function(grunt) {

    var grunt_config = {
        pkg: grunt.file.readJSON('package.json'),
        addtextdomain: {
            main: {
                options: {
                    textdomain: 'envato_setup',
                    updateDomains: true
                },
                files: {
                    src: [
                        'envato_setup/**/*.php',
                        '!envato_setup/importer/*'
                    ]
                }
            }
        }
    };

    grunt.initConfig(grunt_config);

    grunt.loadNpmTasks('grunt-wp-i18n' );
    grunt.loadNpmTasks('grunt-phpcs');


    grunt.registerTask("phpcs-fix-and-check", "Runs the plugin files through PHPCS with WordPress-Extra", function() {
        //grunt.config.set('phpcs', {
        //        application: {
        //            src: ['envato_setup/**/*.php']
        //        },
        //        options: {
        //            bin: 'vendor/bin/phpcbf',
        //            standard: 'WordPress-Extra'
        //        }
        //    }
        //);
        //grunt.task.run('phpcs');
        grunt.config.set('phpcs',{
                application: {
                    src: ['envato_setup/envato_setup.php']
                },

                options: {
                    bin: 'vendor/bin/phpcs',
                    standard: 'WordPress-Extra'
                }
            }
        );
        grunt.task.run('phpcs');
    });



    // todo - run a patch tool that will look for modifications in /build/ compared to /core/ or any existing /theme/ file
    // if changes are found move that changed file back into /theme/ for overrides.


};