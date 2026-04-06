const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('src/Resources/public/build/')
    .setPublicPath('/bundles/logviewer/build/')
    .setManifestKeyPrefix('build/')
    .addEntry('log_viewer', './assets/main.ts')
    .enableVueLoader(() => {}, { version: 3 })
    .enableTypeScriptLoader()
    .addAliases({
        '@': '/app/assets/'
    })
    .disableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction());

module.exports = Encore.getWebpackConfig();
