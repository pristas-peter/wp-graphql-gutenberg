import { v4 } from 'uuid';

export class UUIDGenerator {
	constructor( { id } ) {
		this.id = id;
		this.key = 0;

		const prefixChars = [];
		const idPad = `${ id }`.padStart( 20, '0' );

		for ( let i = 0; i < idPad.length; i++ ) {
			prefixChars.push( idPad[ i ] );

			if ( i === 7 || i === 11 || i === 15 ) {
				prefixChars.push( '-' );
			}
		}

		this.prefix = prefixChars.join( '' );
	}

	createUUID() {
		const key = `${ this.key++ }`;
		return `${ this.prefix }-${ key.padStart( 12, '0' ) }`;
	}
}

export const createVisitor = ( { id } ) => {
	const uuidGenerator = new UUIDGenerator( { id } );

	return ( block ) => {
		let wpGraphqlUUID = block.attributes.wpGraphqlUUID;

		if ( ! wpGraphqlUUID ) {
			if ( wpGraphqlUUID === null ) {
				wpGraphqlUUID = uuidGenerator.createUUID();
			} else {
				wpGraphqlUUID = v4();
			}
		}

		return {
			...block,
			attributes: {
				...block.attributes,
				wpGraphqlUUID,
			},
		};
	};
};
