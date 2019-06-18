import Admin from './admin';

const { __ } = wp.i18n;

function getBlockTypesForSerialization() {
    return wp.blocks.getBlockTypes().map(blockType => lodash.omit(blockType, ['transforms', 'icon']));
}

function getReusableBlocks(blocks, obj = {}) {
    const promises = [];

    blocks.forEach(block => {
        if (block.name === 'core/block') {
            const id = block.attributes.ref;

            promises.push(wp.apiFetch({ path: `/wp/v2/blocks/${id}` })
                .then(wp_block => {
                    obj[id] = wp.blocks.parse(wp_block.content.raw).pop();
                }));
        }

        promises.push(getReusableBlocks(block.innerBlocks, obj));

    });

    return Promise.all(promises)
        .then(() => obj);
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

function prepareData(post_content_blocks) {
    return getReusableBlocks(post_content_blocks)
    .then(reusable_blocks => {
        return {
            post_content_blocks: wp.hooks.applyFilters('wpGraphqlGutenberg.postContentBlocks', post_content_blocks),
            reusable_blocks: wp.hooks.applyFilters('wpGraphqlGutenberg.reusableBlocks', reusable_blocks),
        };
    });
}

wp.wpGraphqlGutenberg = {
    prepareData,
    getReusableBlocks,
    getBlockTypesForSerialization,
}

wp.domReady(() => {
    const admin = document.getElementById('wp-graphql-gutenberg-admin');

    if (admin) {
        wp.element.render(<Admin />, admin);

    } else {
        wp.apiFetch.use((options, next) => {
            if (isEditorUpdateRequest(options)) {
                if (options.data.content) {
                    return prepareData(wp.blocks.parse(options.data.content)).then(data => {
                        Object.assign(options.data, {
                            wp_graphql_gutenberg: {
                                ...data,
                                block_types: getBlockTypesForSerialization(),
                            },
                        });

                        return next(options);
                    });
                }
            }

            return next(options);
        });

        if (shouldForceUpdate()) {
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
