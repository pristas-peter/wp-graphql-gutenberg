import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, withNotices } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

import styled from 'styled-components';
import { actions } from './admin';

const Container = styled.div`
	width: 100%;
	background-color: white;
	padding: 15px;
	&,
	* {
		box-sizing: border-box;
	}
`;

const Heading = styled.h1``;

const TableContainer = styled.div`
	padding: 5px 15px 2px;
`;

const Table = styled.table`
	margin: 0;
	width: 100%;
	border: 1px solid #eee;
`;

const TBody = styled.tbody``;

const TRow = styled.tr``;

const TData = styled.td`
	padding: 0 15px;
`;

class Settings extends Component {
	constructor( props, context ) {
		super( props, context );

		this.state = {
			total: null,
			progress: null,
			isBusy: false,
		};

		this.onUpdate = this.onUpdate.bind( this );
	}

	onUpdate() {
		const {
			noticeOperations: { createErrorNotice, createNotice },
		} = this.props;

		this.setState(
			{
				isBusy: true,
			},
			() => {
				const iframe = document.createElement( 'iframe' );

				const context = {
					timeout: null,
				};

				const timeoutPromise = new Promise( ( _, reject ) => {
					context.timeout = setTimeout( reject, 1000 * 15 );
				} );

				return Promise.race( [
					new Promise( ( resolve, reject ) => {
						iframe.setAttribute( 'style', 'display: none;' );
						iframe.setAttribute(
							'src',
							`${ window.wpGraphqlGutenberg.adminUrl }post-new.php?post_type=${ window.wpGraphqlGutenberg.adminPostType }&action=edit&wpGraphqlGutenbergServer=true`
						);

						iframe.admin = {
							queue: [
								{
									action: actions.HEARTBEAT,
									onComplete: () => {
										clearTimeout( context.timeout );
									},
									onError: reject,
								},
								{
									options: {},
									action: actions.GET_BLOCK_REGISTRY,
									onComplete: ( result ) => {
										apiFetch( {
											path: 'wp-graphql-gutenberg/v1/block-registry',
											method: 'POST',
											data: {
												block_types: result,
											},
										} )
											.then( resolve )
											.catch( reject );
									},
									onError: reject,
								},
							],
						};

						iframe.onerror = reject;
						document.body.appendChild( iframe );
					} ).finally( () => {
						if ( iframe.parentNode ) {
							iframe.parentNode.removeChild( iframe );
						}
					} ),
					timeoutPromise,
				] )
					.then( () => {
						createNotice( {
							status: 'success',
							content: __( 'Block registry has been updated.', 'wp-graphql-gutenberg' ),
						} );
					} )
					.catch( ( err ) => {
						createErrorNotice( ( err && err.message ) || __( 'Update failed.', 'wp-graphql-gutenberg' ) );
					} )
					.finally( () => {
						this.setState( {
							isBusy: false,
							total: null,
							progress: null,
						} );
					} );
			}
		);
	}

	render() {
		const { noticeUI } = this.props;
		const { isBusy } = this.state;

		return (
			<Container>
				<Heading>{ __( 'WP GraphQL Gutenberg Admin', 'wp-graphql-gutenberg' ) }</Heading>
				{ noticeUI }
				<TableContainer>
					<Table>
						<TBody>
							<TRow>
								<TData>{ __( 'Update block registry', 'wp-graphql-gutenberg' ) }</TData>
								<TData>
									<Button
										isPrimary={ true }
										isLarge={ true }
										isBusy={ isBusy }
										onClick={ this.onUpdate }
										disabled={ isBusy }
									>
										{ __( 'Update', 'wp-graphql-gutenberg' ) }
									</Button>
								</TData>
								<TData>
									{ this.state.progress !== null && this.state.total !== null
										? `${ this.state.progress } / ${ this.state.total }`
										: null }
								</TData>
							</TRow>
						</TBody>
					</Table>
				</TableContainer>
			</Container>
		);
	}
}

export default withNotices( Settings );
