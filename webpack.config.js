const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

/**
 * WordPress 6.5 registers React and ReactDOM, but not the JSX-runtime handle
 * introduced later. Bundle only that small React adapter while continuing to
 * externalize React itself to WordPress core.
 */
module.exports = {
	...defaultConfig,
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...defaultConfig.resolve.alias,
			'react/jsx-runtime': path.resolve(
				__dirname,
				'src/js/react-jsx-runtime.js'
			),
			'react/jsx-dev-runtime': path.resolve(
				__dirname,
				'src/js/react-jsx-runtime.js'
			),
		},
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !==
				'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			requestToExternal( request ) {
				if (
					request === 'react/jsx-runtime' ||
					request === 'react/jsx-dev-runtime'
				) {
					return false;
				}

				return undefined;
			},
		} ),
	],
};
