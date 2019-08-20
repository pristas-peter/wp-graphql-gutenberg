/* eslint-disable */
import Admin from './admin';

const { __ } = wp.i18n;

function getBlockTypesForSerialization() {
    return wp.blocks.getBlockTypes().map(blockType => lodash.omit(blockType, ['transforms', 'icon']));
}

function getReusableBlocks(blocks, blocksById = {}) {
    const promises = [];

    blocks.forEach(block => {
        if (block.name === 'core/block') {
            const id = block.attributes.ref;

            promises.push(wp.apiFetch({ path: `/wp/v2/blocks/${id}` })
                .then(wp_block => {
                    blocksById[id] = wp.blocks.parse(wp_block.content.raw).pop();
                }));
        }

        promises.push(getReusableBlocks(block.innerBlocks, blocksById));

    });

    return Promise.all(promises)
        .then(() => blocksById);
}


function getPostTypeRestBase() {
    const registry = wp.data && wp.data.select("core/editor");
    const currentPostType = registry && registry.getCurrentPostType();

    if (currentPostType) {
        const postType = wp.data.select("core").getPostType(currentPostType);

        if (postType) {
            return postType.rest_base;
        }
    }

    return null;
}

function isEditorUpdateRequest(options) {
    const restBase = getPostTypeRestBase();
    if (restBase) {
        const regexp = new RegExp(`^\/wp\/v2\/${restBase}\/`);
        return regexp.test(options.path) && options.method === 'PUT';
    }

    return false;
}

function isReusableBlockUpdateRequest(options) {
    return options.method === 'PUT' && /\/wp\/v[0-9]+\/blocks\/[0-9]+(\?.+)*$/.test(options.path);
}

function shouldForceUpdate() {
    return window.location.search.substring(1).split('&').indexOf('wpGraphqlGutenbergForceUpdate') > -1;
}

function editorReady(cb) {
    let interval;

    const intervalCb = () => {
        const { id } = wp.data.select("core/editor").getCurrentPost() || {};

        if (id) {
            if (interval) {
                clearInterval(interval);
            }

            cb();
            return true;
        }

        return false;
    }

    if (!intervalCb()) {
        interval = setInterval(intervalCb, 250);
    }
}

const visitBlocks = (blocks, visitor) => {
    blocks.forEach(block => {
        visitor(block);

        if (block.innerBlocks) {
            visitBlocks(block.innerBlocks, visitor); 
        }
    });

    return blocks;
}

function preparePostContentBlocks(blocks) {
    return wp.hooks.applyFilters('wpGraphqlGutenberg.postContentBlocks', visitBlocks(blocks, block => block.parent = wp.data.select("core/editor").getCurrentPost().id));
}

function prepareReusableBlock(block) {
    return wp.hooks.applyFilters('wpGraphqlGutenberg.reusableBlock', block);
}

function prepareReusableBlocks(blocksById) {
    return wp.hooks.applyFilters('wpGraphqlGutenberg.reusableBlocks', blocksById);
}

wp.wpGraphqlGutenberg = {
    visitBlocks,
    preparePostContentBlocks,
    prepareReusableBlock,
    prepareReusableBlocks,
    getBlockTypesForSerialization,
}

wp.domReady(() => {
    const admin = document.getElementById('wp-graphql-gutenberg-admin');
    
    if (admin) {
        wp.element.render(<Admin />, admin);

    } else {
        const forceUpdate = shouldForceUpdate();
        
        wp.apiFetch.use((options, next) => {
            if (isEditorUpdateRequest(options)) {
                if (options.data.content) {
                    const blocks = wp.blocks.parse(options.data.content);
                    return Promise.all([
                        preparePostContentBlocks(blocks),
                        forceUpdate && getReusableBlocks(blocks).then(prepareReusableBlocks)
                    ]).then(([postContentBlocks, reusableBlocks]) => {
                        const data = {
                            block_types: getBlockTypesForSerialization(),
                            post_content_blocks: postContentBlocks,
                        };

                        if (reusableBlocks) {
                            Object.assign(data, {
                                reusable_blocks: reusableBlocks,
                            });
                        }

                        Object.assign(options.data, {
                            wp_graphql_gutenberg: data,
                        });

                        return next(options);
                    });
                }
            }

            if (isReusableBlockUpdateRequest(options)) {
                if (options.data.content) {
                    const [block] = wp.blocks.parse(options.data.content);

                    Object.assign(options.data, {
                        wp_graphql_gutenberg: {
                            reusable_block: prepareReusableBlock(block),
                            block_types: getBlockTypesForSerialization(),
                        },
                    });

                    return next(options);
                }
            }

            return next(options);
        });

        if (forceUpdate) {
            editorReady(() => {
                const iframe = window.frameElement;
                const admin = iframe && window.frameElement.wpGraphqlGutenbergAdmin;

                const { id, content } = wp.data.select("core/editor").getCurrentPost();
                const restBase = getPostTypeRestBase();

                const promise = restBase ? wp.apiFetch({
                    path: `/wp/v2/${restBase}/${id}`,
                    method: 'PUT',
                    data: {
                        content,
                    }
                }) : Promise.reject(new Error(__('Could not detect post type\' rest base.', 'wp-graphql-gutenberg')));

                if (admin) {
                    admin.handleUpdatePromise(iframe, promise);
                }
            });
        }
    }
});
