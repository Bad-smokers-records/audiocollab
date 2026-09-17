const path = require('path')
const { VueLoaderPlugin } = require('vue-loader')

module.exports = {
    entry: {
        'audiocollab-main': path.resolve(__dirname, 'src/main.js'),
        'audiocollab-admin-settings': path.resolve(__dirname, 'src/admin-settings.js'),
        'audiocollab-dashboard': path.resolve(__dirname, 'src/dashboard.js'),
    },
    output: {
        path: path.resolve(__dirname, 'js'),
        filename: '[name].js',
    },
    resolve: {
        alias: {
            'vue$': 'vue/dist/vue.esm.js',
        },
        extensions: ['.js', '.vue'],
    },
    module: {
        rules: [
            {
                test: /\.vue$/,
                loader: 'vue-loader',
            },
            {
                test: /\.js$/,
                exclude: /node_modules/,
                loader: 'babel-loader',
            },
            {
                test: /\.css$/,
                use: ['vue-style-loader', 'css-loader'],
            },
        ],
    },
    plugins: [
        new VueLoaderPlugin(),
    ],
}
