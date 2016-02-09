/* jshint node:true */
module.exports = function( grunt ){
	'use strict';

	grunt.initConfig({

		shell: {
			options: {
				stdout: true,
				stderr: true
			},
			generatepot: {
				command: [
					'makepot'
				].join( '&&' )
			}
		},

	});

	// Load NPM tasks to be used here
	grunt.loadNpmTasks( 'grunt-shell' );

	// Just an alias for pot file generation
	grunt.registerTask( 'pot', [
		'shell:generatepot'
	]);

};
