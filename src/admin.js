import styled from 'styled-components';

const { __, sprintf } = wp.i18n;
const { Button, withNotices } = wp.components;
const { apiFetch } = wp;

const Container = styled.div`
	width: 100%;
	background-color: white;
	padding: 15px;
	&, * {
		box-sizing: border-box;
	}
`;

const Heading = styled.h1`

`;

const TableContainer = styled.div`
	padding: 5px 15px 2px;
`;

const Table = styled.table`
	margin: 0;
	width: 100%;
	border: 1px solid #eee;
`;

const TBody = styled.tbody`

`;

const TRow = styled.tr`
`;

const TData = styled.td`
	padding: 0 15px;
`;

const { Component } = wp.element;

class Admin extends Component {
	state = {
		total: null,
		progress: null,
		isBusy: false,
	};

	updatePromises;

	onUpdate = () => {
		const { noticeOperations: { createErrorNotice, createNotice } } = this.props;

		this.updatePromises = new WeakMap();

		this.setState( {
			isBusy: true,
		}, () => {
			apiFetch( { path: 'wp-graphql-gutenberg/v1/editor-posts' } )
				.then( ids => {
					return ids.reduce((acc, id, i) => acc.then(() =>
						new Promise( ( resolve, reject ) => {
							const next = () => {
								const iframe = document.createElement( 'iframe' );
								iframe.wpGraphqlGutenbergAdmin = this;
								iframe.setAttribute( 'style', 'display: none;' );
								iframe.setAttribute( 'src', `${ window.wpGraphqlGutenberg.adminUrl }post.php?post=${ id }&action=edit&wpGraphqlGutenbergForceUpdate` );
	
								document.body.appendChild( iframe );
								this.updatePromises.set( iframe, { resolve, reject } );
							};
	
							if ( i === 0 ) {
								this.setState( {
									total: ids.length,
									progress: 0,
								}, next );
							} else {
								next();
							}
						}
						).catch(err => {
							err.message = `${sprintf(__('Failed to update post with id %d.', "wp-graphql-gutenberg"), id)} ${err.message}`;
							return Promise.reject(err);
						})), 
					Promise.resolve());
				})
				.then( () => {
					createNotice( { status: 'success', content: __( 'All posts have been updated.' ) } );
				} )
				.catch( err => {
					createErrorNotice( (err && err.message) ||  __( 'Update failded.' ) );
				} )
				.finally( () => {
					this.setState( {
						isBusy: false,
						total: null,
						progress: null,
					} );
				} );
		} );
	}

	handleUpdatePromise = ( iframe, promise ) => {
		promise
			.then( () => {
				const { resolve } = this.updatePromises.get( iframe ) || {};

				if ( resolve ) {
					this.setState( state => ( {
						progress: state.progress !== null ? state.progress + 1 : null,
					} ) );

					resolve();
				}
			} )
			.catch( err => {
				const { reject } = this.updatePromises.get( iframe ) || {};

				if ( reject ) {
					reject( err );
				}
			} )
			.finally( () => {
				if ( iframe.parentNode ) {
					iframe.parentNode.removeChild( iframe );
				}
			} );
	}

	render() {
		const { noticeUI } = this.props;
		const { isBusy } = this.state;

		return (
			<Container>
				<Heading>{ __( 'WP GraphQL Gutenberg Admin' ) }</Heading>
				{ noticeUI }
				<TableContainer>
					<Table>
						<TBody>
							<TRow>
								<TData>
									{ __( 'Update all posts which support editor' ) }
								</TData>
								<TData>
									<Button
										isPrimary={ true }
										isLarge={ true }
										isBusy={ isBusy }
										onClick={ this.onUpdate }
										disabled={ isBusy }
									>
										{ __( 'Update' ) }
									</Button>
								</TData>
								<TData>
									{ this.state.progress !== null && this.state.total !== null ?
										`${ this.state.progress } / ${ this.state.total }` : null }
								</TData>
							</TRow>
						</TBody>
					</Table>
				</TableContainer>
			</Container>
		);
	}
}

export default withNotices( Admin );
