const path = require("path");

module.exports = {
	entry: { index: path.resolve(__dirname, "src", "captainhooks.js") },
	output: { filename: 'captainhooks.js' },
	module: {
    rules: [
      {
        test: /\.scss$/,
        use: ["style-loader", "css-loader", "sass-loader"]
      },
			{
        test: /\.js$/,
        exclude: /node_modules/,
        use: ["babel-loader"]
      }
    ]
  },
}