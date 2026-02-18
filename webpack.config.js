/**
 * Webpack configuration for EU AI Act Ready plugin.
 *
 * @package EUAIACTREADY
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path          = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'admin/admin': [
			path.resolve( process.cwd(), 'src/admin', 'admin.js' ),
			path.resolve( process.cwd(), 'src/admin', 'admin.scss' ),
		],
		'admin/settings-preview': path.resolve( process.cwd(), 'src/admin', 'settings-preview.js' ),
		'admin/media-recheck': path.resolve( process.cwd(), 'src/admin', 'media-recheck.js' ),
		'assets/eu-ai-act-ready': [
			path.resolve( process.cwd(), 'src/assets', 'eu-ai-act-ready.js' ),
			path.resolve( process.cwd(), 'src/assets', 'eu-ai-act-ready.scss' ),
		],
		'assets/chatbot-transparency': path.resolve( process.cwd(), 'src/assets', 'chatbot-transparency.js' ),
	},
	output: {
		filename: '[name].js',
		path: path.resolve( process.cwd(), 'build' ),
	},
};
