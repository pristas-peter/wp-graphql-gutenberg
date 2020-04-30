exports.createSchemaCustomization = ({ actions }) => {
	actions.createTypes(`
        type File implements Node @infer {
            childMarkdownRemark: MarkdownRemark
        }

        type MarkdownRemark implements Node @infer {
            frontmatter: MarkdownRemarkFrontmatter
            fields: MarkdownRemarkFields
        }

        type MarkdownRemarkFields {
            image: String
            version: String
            slug: String
            graphManagerUrl: String
            versionRef: String
            sidebarTitle: String
        }

        type MarkdownRemarkFrontmatter {
            title: String
            subtitle: String
            description: String
        }
    `)
}
