const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CompressionPlugin = require("compression-webpack-plugin");
const isDevelopment = true;

module.exports = {
	mode: isDevelopment ? 'development' : 'production',
	entry: [
		'./assets/js/main.js',
		'./assets/scss/main.scss',
	],
	output: {
		filename: 'main.js',
		path: path.resolve(__dirname, 'dist'),
		publicPath: "/dist/"
	},
	module: {
		rules: [
			{
				test: /\.(scss)$/,
				use: [
					MiniCssExtractPlugin.loader,
					{
						loader: "css-loader",
						options: {
							sourceMap: true,
							importLoaders: 1,
						}
					},
					{
						loader: "postcss-loader",
						options: {
							sourceMap: true,
						}
					},
					{
						loader: "sass-loader",
						options: {
							//sourceMap: true
						}
					}
				]
			},
		],
	},
	resolve: {
		modules: [
			"node_modules",
			path.resolve(__dirname, "dist")
		],
		extensions: [".js", ".json", ".jsx", ".css", ".scss"]
	},
	plugins: [
		new MiniCssExtractPlugin({
			filename: isDevelopment ? '[name].css' : '[name].[hash].css',
			chunkFilename: isDevelopment ? '[id].css' : '[id].[hash].css'
		}),
		new CompressionPlugin({
			test: /\.(js|css)/
		})
	],
	devtool: "source-map",
};
