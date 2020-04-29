module.exports = {
	siteMetadata: {
		title: `WpGraphQL Gutenberg Docs`,
		description: `wp-graphql-gutenberg documentation.`,
	},
	plugins: [
		{
			resolve: `gatsby-theme-apollo-docs`,
			options: {
				defaultVersion: `0.2.0`,
				// versions: {
				//   "0.1 Beta": `version-0.1`,
				// },
				// algoliaApiKey: `4575706171508518950c4bf031729fc9`,
				// algoliaIndexName: `wpgg`,
				siteName: `WpGraphQL Gutenberg Docs`,
				menuTitle: `WpGraphQL Gutenberg Menu`,
				subtitle: `WpGraphQL Gutenberg`,
				// baseUrl: `https://gwpg-docs.netlify.com`,
				root: __dirname,
				description: `WpGraphQL Gutenberg documentation`,
				// githubRepo: `GatsbyWPGutenberg/gatsby-wordpress-gutenberg/docs`,
				// logoLink: `https://gwpg-docs.netlify.com`,
				// navConfig: {
				// 	Docs: {
				// 		url: `https://gwpg.netlify.com`,
				// 		description: `The GatsbyWPGutenberg docs`,
				// 	},
				// 	Github: {
				// 		url: `https://github.com/GatsbyWPGutenberg`,
				// 		description: `GatsbyWPGutenberg on Github`,
				// 	},
				// },
				// footerNavConfig: {
				//   SomeFooterLink: {
				//     href: `https://github.com/wpgg`,
				//     target: `_blank`,
				//     rel: `noopener noreferrer`,
				//   },
				// },
				sidebarCategories: {
					null: [`index`],
					"Getting Started": [
						`getting-started/installation`,
						`getting-started/overview`,
						`getting-started/how-to-query`,
						`getting-started/revisions-and-previews`,
					],
					Previews: [`previews/overview`],
					Server: [
						`server/overview`,
						`server/installation`,
						`server/lambda-functions`,
					],
					Addons: [
						`addons/acf-blocks`,
						`addons/extending`,
						`addons/woocommerce`,
					],
					Contributing: [`contributing/contributing`],
					"API Reference": [`api/api`],
				},
			},
		},
		`gatsby-plugin-sharp`,
		{
			resolve: `gatsby-transformer-remark`,
			options: {
				plugins: [
					{
						resolve: `gatsby-remark-images`,
						options: {
							maxWidth: 800,
						},
					},
				],
			},
		},
		`gatsby-plugin-preval`,
	],
}
