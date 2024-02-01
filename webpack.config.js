const path = require('path');
const fs = require('fs');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CompressionPlugin = require("compression-webpack-plugin");
const ForkTsCheckerWebpackPlugin = require('fork-ts-checker-webpack-plugin');
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
//const WorkboxPlugin = require('workbox-webpack-plugin');
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin");
const TerserPlugin = require("terser-webpack-plugin");
const isDevelopment = false;

//const genRanHex = (size = 24) => [...Array(size)].map(() => Math.floor(Math.random() * 16).toString(16)).join('');

const files = fs.readdirSync(path.resolve(__dirname, 'assets/scss/pages/'))
	.map(file => {
		const name = 'pages/' + file.replace('.scss', '');
		return [
			name,
			{
				import: './assets/scss/pages/' + file,
				runtime: false,
			}
		]
	});

const resultFiles = fs.readdirSync(path.resolve(__dirname, 'assets/scss/results/templates/'))
	.map(file => {
		const name = 'results/' + file.replace('.scss', '');
		return [
			name,
			{
				import: './assets/scss/results/templates/' + file,
				runtime: false,
			}
		]
	});

const moduleFiles = fs.readdirSync(path.resolve(__dirname, 'modules/'))
	.map(module => {
		const assetPath = path.resolve(__dirname, 'modules/' + module + '/assets');
		console.log('path', assetPath);
		const moduleAssets = {};
		let count = 0;
		if (!fs.existsSync(assetPath)) return [];
		const assets = fs.readdirSync(assetPath);
		console.log('assets', assets);
		if (assets.includes('js')) {
			fs.readdirSync(assetPath + "/js")
				.forEach(file => {
					if ((file.endsWith('.js') || file.endsWith('.ts')) && !file.startsWith('_')) {
						const name = file.replace('.js', '').replace('.ts', '');
						if (!moduleAssets[name]) {
                            moduleAssets[name] = {
                                import: [],
                                runtime: false,
                            };
						}
                        moduleAssets[name].import.push(`./modules/${module}/assets/js/${file}`);
						count++;
					}
				});
		}
		if (assets.includes('css')) {
			fs.readdirSync(assetPath + "/css")
				.forEach(file => {
					if ((file.endsWith('.css') || file.endsWith('.scss')) && !file.startsWith('_')) {
						const name = file.replace('.css', '').replace('.scss', '');
						if (!moduleAssets[name]) {
                            moduleAssets[name] = {
                                import: [],
                                runtime: false,
                            };
						}
                        moduleAssets[name].import.push(`./modules/${module}/assets/css/${file}`);
						count++;
					}
				});
		}

		if (count > 0) {
			return [module, moduleAssets];
		}
		return [];
	});

console.log('modules', moduleFiles);

let entry = {
	main: [
		'./assets/js/main.ts',
		'./assets/scss/main.scss',
	],
};

files.forEach(([name, data]) => {
	entry[name] = data;
});
resultFiles.forEach(([name, data]) => {
	entry[name] = data;
});

moduleFiles.forEach(module => {
	if (module.length < 2) return;
	const [moduleName, files] = module;
	const name = 'modules/' + moduleName.toLowerCase();
	Object.entries(files).forEach(([fileName, data]) => {
		entry[name + '/' + fileName] = data;
	})
})

console.log(entry);

module.exports = {
    mode: isDevelopment ? 'development' : 'production',
	entry,
	output: {
		filename: '[name].js',
		path: path.resolve(__dirname, 'dist'),
		publicPath: "/dist/"
	},
	module: {
		rules: [
			{
				test: /\.tsx?$/,
				loader: 'ts-loader'
			},
			{
                test: /\.(s?css)$/,
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
		extensions: [".ts", ".tsx", ".js", ".json", ".jsx", ".css", ".scss"]
	},
	plugins: [
        /*new WorkboxPlugin.GenerateSW({
            swDest: 'service-worker.js',
            //navigationPreload: true,
            clientsClaim: true,
            //skipWaiting: true,
            cleanupOutdatedCaches: true,
            cacheId: genRanHex(),
            runtimeCaching: [
                {
                    handler: 'NetworkFirst',
                    urlPattern: /\.(?:webm|ogg|oga|mp3|wav|aiff|flac|mp4|m4a|aac|opus|webp)/
                }
            ]
        }),*/
		new ForkTsCheckerWebpackPlugin(),
		new MiniCssExtractPlugin({
			filename: '[name].css',
			chunkFilename: '[id].css'
		}),
		new CompressionPlugin({
			test: /\.(js|ts|css)/
		}),
		new BundleAnalyzerPlugin({
			analyzerMode: 'static',
			generateStatsFile: true,
			openAnalyzer: false,
		}),
	],
	cache: {
		type: 'filesystem',
		cacheDirectory: path.resolve(__dirname, 'temp/webpack'),
	},
	devtool: "source-map",
	optimization: {
        minimize: true,
		usedExports: true,
        runtimeChunk: false,
        removeAvailableModules: true,
        moduleIds: 'deterministic',
        splitChunks: {
            chunks: 'all',
			usedExports: true,
			cacheGroups: {
				vendor: {
                    test: /[\\/]node_modules[\\/](flatpickr|@fortawesome|sortablejs)[\\/]/,
					name: 'vendors',
					chunks: 'all',
				},
				bootstrap: {
					test: /[\\/]node_modules[\\/](bootstrap|@popperjs)[\\/]/,
					name: 'bootstrap',
					chunks: 'all',
                },
                common: {
                    test: /[\\/]assets[\\/]js[\\/]includes[\\/]/,
                    name: 'common',
                    chunks: 'all',
                },
            },
        },
        minimizer: [
            new TerserPlugin({
                parallel: true,
                terserOptions: {
                    compress: {
                        passes: 2,
                    },
                },
            }),
            new CssMinimizerPlugin(),
        ]
    },
};
