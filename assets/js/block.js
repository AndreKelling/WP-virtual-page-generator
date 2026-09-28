( function( blocks, element, components, data ) {
    var el = element.createElement;
    var SelectControl = components.SelectControl;
    var useSelect = data.useSelect;

    blocks.registerBlockType( 'vpg/service-list', {
        title: 'VPG Services by Location',
        icon: 'list-view',
        category: 'widgets',
        attributes: {
            locationId: {
                type: 'string',
                default: ''
            },
            templateId: {
                type: 'string',
                default: ''
            }
        },
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var locations = useSelect( function( select ) {
                return select( 'core' ).getEntityRecords( 'postType', 'vpg_location', { per_page: -1 } );
            } );

            var templates = useSelect( function( select ) {
                return select( 'core' ).getEntityRecords( 'postType', 'vpg_template', { per_page: -1 } );
            } );

            var locationOptions = [ { label: 'Select a Location', value: '' } ];
            if ( locations ) {
                locations.forEach( function( location ) {
                    locationOptions.push( { label: location.title.rendered, value: location.id.toString() } );
                } );
            }

            var templateOptions = [ { label: 'Select a Template', value: '' } ];
            if ( templates ) {
                templates.forEach( function( template ) {
                    templateOptions.push( { label: template.title.rendered, value: template.id.toString() } );
                } );
            }

            return el(
                'div',
                { className: props.className },
                el( 'h4', {}, 'VPG Services by Location' ),
                el( SelectControl, {
                    label: 'Location',
                    value: attributes.locationId,
                    options: locationOptions,
                    onChange: function( value ) {
                        setAttributes( { locationId: value } );
                    }
                } ),
                el( SelectControl, {
                    label: 'Template',
                    value: attributes.templateId,
                    options: templateOptions,
                    onChange: function( value ) {
                        setAttributes( { templateId: value } );
                    }
                } )
            );
        },
        save: function() {
            return null; // Rendered via PHP
        }
    } );
} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.data
);
