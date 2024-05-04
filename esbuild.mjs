import * as esbuild from 'esbuild';
import {sassPlugin} from 'esbuild-sass-plugin';
import postcss from "postcss";
import autoprefixer from "autoprefixer";
import fs from 'node:fs';
import {fontawesomeSubset} from "fontawesome-subset";
import {compress} from 'esbuild-plugin-compress';
import cssnanoPlugin from "cssnano";
import path from "path";
import {injectManifest} from "workbox-build";

const watch = process.argv.includes('watch');

await fontawesomeSubset(
        {
            brands: ['discord'],
            regular: ['calendar', 'circle-xmark', 'circle-check'],
            solid: [
                'music',
                'angle-down',
                'angle-left',
                'angle-right',
                'angle-up',
                'ban',
                'cancel',
                'check',
                'circle-info',
                'circle-question',
                'download',
                'edit',
                'eye',
                'file-pdf',
                'filter',
                'gear',
                'gun',
                'info',
                'list',
                'location-dot',
                'magnifying-glass-plus',
                'medal',
                'pen-to-square',
                'play',
                'plus',
                'question',
                'ranking-star',
                'right-from-bracket',
                'right-to-bracket',
                'share',
                'star',
                'stop',
                'tag',
                'trophy',
                'user',
                'user-clock',
                'user-plus',
                'xmark',
                'moon',
                'sun',
                'print',
                'display',
                'cog',
                'bars',
                'save',
                'trash',
                'search',
                'shuffle',
                'chair',
                'soap',
                'upload',
                'cloud',
                'close',
                'rotate',
                'box-archive',
                'bullseye',
                'arrows-rotate',
                'cloud-arrow-up',
                'user-group',
                'layer-group',
                'people-group',
                'clipboard-list',
                'grip-lines',
                'circle-chevron-up',
                'circle-play',
                'circle-stop',
                'circle-exclamation',
            ],
        },
        "assets/fonts",
        {
            package: 'free',
            targetFormats: ['woff2', "woff", 'sfnt'],
        }
);

/**
 * @type {{in:string,out:string}[][]}
 */
const moduleFiles = fs.readdirSync('./modules/')
        .map(module => {
            const assetPath = './modules/' + module + '/assets';
            console.log('path', assetPath);
            const moduleAssets = [];
            let count = 0;
            if (!fs.existsSync(assetPath)) return [];
            const assets = fs.readdirSync(assetPath);
            console.log('assets', assets);

            if (assets.includes('js')) {
                fs.readdirSync(assetPath + "/js")
                        .forEach(file => {
                            if ((file.endsWith('.js') || file.endsWith('.ts')) && !file.startsWith('_')) {
                                const name = file.replace('.js', '').replace('.ts', '');
                                moduleAssets.push(
                                        {
                                            in: `./modules/${module}/assets/js/${file}`,
                                            out: `modules/${module.toLowerCase()}/${name}`,
                                        }
                                );
                                count++;
                            }
                        });
            }
            if (assets.includes('css')) {
                fs.readdirSync(assetPath + "/css")
                        .forEach(file => {
                            if ((file.endsWith('.css') || file.endsWith('.scss')) && !file.startsWith('_')) {
                                const name = file.replace('.css', '').replace('.scss', '');
                                moduleAssets.push(
                                        {
                                            in: `./modules/${module}/assets/css/${file}`,
                                            out: `modules/${module.toLowerCase()}/${name}`,
                                        }
                                );
                                count++;
                            }
                        });
            }
            return moduleAssets;
        })
        .filter(assets => assets.length > 0)
        .flat();

const entryPoints = [
    {out: 'main', in: 'assets/js/main.ts'},
    {out: 'main', in: 'assets/scss/main.scss'},
    //{out: 'bootstrap', in: 'assets/scss/bootstrap.scss'},
    {out: 'fontawesome', in: 'assets/scss/fontawesome.scss'},
    ...fs.readdirSync('assets/scss/pages/')
            .filter(file => ['.css', '.scss'].includes(path.extname(file)))
            .map(file => {
                return {
                    out: 'pages/' + file.replace('.scss', ''),
                    in: './assets/scss/pages/' + file
                }
            }),
    ...fs.readdirSync('assets/js/gate/')
            .filter(file => ['.ts'].includes(path.extname(file)) && !file.includes('gateScreen.ts'))
            .map(file => {
                return {
                    out: 'gate/' + file.replace('.ts', ''),
                    in: './assets/js/gate/' + file
                }
            }),
    ...fs.readdirSync('assets/scss/gate/')
            .filter(file => ['.scss'].includes(path.extname(file)))
            .map(file => {
                return {
                    out: 'gate/' + file.replace('.scss', ''),
                    in: './assets/scss/gate/' + file
                }
            }),
    ...fs.readdirSync('assets/scss/results/templates/').map(file => {
        return {
            out: 'results/' + file.replace('.scss', ''),
            in: './assets/scss/results/templates/' + file
        }
    }),
    ...moduleFiles,
];

console.log(entryPoints);

const buildOptions = {
    entryPoints,
    bundle: true,
    format: 'esm',
    splitting: true,
    chunkNames: 'chunks/[name]_[hash]',
    minify: true,
    outdir: 'dist',
    target: 'esnext',
    sourcemap: true,
    metafile: true,
    color: true,
    treeShaking: true,
    external: [
        '/assets/fonts/*',
        '/assets/images/*'
    ],
    plugins: [
        sassPlugin({
            embedded: true,
            cssImports: true,
            async transform(source, _) {
                const {css} = await postcss([autoprefixer, cssnanoPlugin({preset: 'default'})])
                        .process(source, {
                            from: 'assets/scss',
                            to: 'dist/scss'
                        })
                return css
            }
        }),
    ]
};

const compressOptions = {
    ...buildOptions,
    write: false,
    plugins: [
        ...buildOptions.plugins,
        compress({
            outputDir: '',
            brotli: false,
            gzip: true,
            exclude: ['**/*.map'],
        }),
    ]
}

const ctx = await esbuild.context(buildOptions);

if (watch) {
    await ctx.watch();
    console.log('watching...')
} else {
    const result = await ctx.rebuild();
    const compressResult = await esbuild.build(compressOptions);
    fs.writeFileSync('dist/meta.json', JSON.stringify(result.metafile));
    await ctx.dispose();

    await esbuild.build({
        entryPoints: ['assets/js/sw/service-worker.ts'],
        bundle: true,
        sourcemap: true,
        color: true,
        format: 'esm',
        target: 'esnext',
        minify: true,
        outfile: 'temp/service-worker.js',
    })

    injectManifest({
        swDest: 'dist/service-worker.js',
        swSrc: 'temp/service-worker.js',
        globDirectory: './dist',
        globPatterns: [
            'pages/*',
            'chunks/*',
            '*',
            '../assets/fonts/*',
            '../assets/images/*',
        ]
    }).then(({count, size, warnings}) => {
        if (warnings.length > 0) {
            console.warn(
                    'Warnings encountered while injecting the manifest:',
                    warnings.join('\n')
            );
        }

        console.log(`Injected a manifest which will precache ${count} files, totaling ${size} bytes.`);
    });
}