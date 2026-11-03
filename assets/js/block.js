( function( blocks, element, components, data, blockEditor ) {
    const el = element.createElement;
    const SelectControl = components.SelectControl;
    const useSelect = data.useSelect;
    const useBlockProps = ( blockEditor && blockEditor.useBlockProps ) ? blockEditor.useBlockProps : function() { return {}; };

    blocks.registerBlockType( 'vpg/services-by-location', {
        apiVersion: 3,
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
            const attributes = props.attributes;
            const setAttributes = props.setAttributes;
            const blockProps = useBlockProps();

            const locations = useSelect( function( select ) {
                return select( 'core' ).getEntityRecords( 'postType', 'vpg_location', { per_page: -1 } );
            } );

            const templates = useSelect( function( select ) {
                return select( 'core' ).getEntityRecords( 'postType', 'vpg_template', { per_page: -1 } );
            } );

            const locationOptions = [ { label: 'Select a Location', value: '' } ];
            if ( locations ) {
                locations.forEach( function( location ) {
                    locationOptions.push( { label: location.title.rendered, value: location.id.toString() } );
                } );
            }

            const templateOptions = [ { label: 'Select a Template', value: '' } ];
            if ( templates ) {
                templates.forEach( function( template ) {
                    templateOptions.push( { label: template.title.rendered, value: template.id.toString() } );
                } );
            }

            return el(
                'div',
                blockProps,
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

    blocks.registerBlockType( 'vpg/locations-by-service', {
        apiVersion: 3,
        title: 'VPG Locations by Service',
        icon: 'location',
        category: 'widgets',
        attributes: {
            serviceId: {
                type: 'string',
                default: ''
            },
            templateId: {
                type: 'string',
                default: ''
            }
        },
        edit: function( props ) {
            const attributes = props.attributes;
            const setAttributes = props.setAttributes;
            const blockProps = useBlockProps();

            const services = useSelect( function( select ) {
                return select( 'core' ).getEntityRecords( 'postType', 'vpg_service', { per_page: -1 } );
            } );

            const templates = useSelect( function( select ) {
                return select( 'core' ).getEntityRecords( 'postType', 'vpg_template', { per_page: -1 } );
            } );

            const serviceOptions = [ { label: 'Select a Service', value: '' } ];
            if ( services ) {
                services.forEach( function( service ) {
                    serviceOptions.push( { label: service.title.rendered, value: service.id.toString() } );
                } );
            }

            const templateOptions = [ { label: 'Select a Template', value: '' } ];
            if ( templates ) {
                templates.forEach( function( template ) {
                    templateOptions.push( { label: template.title.rendered, value: template.id.toString() } );
                } );
            }

            return el(
                'div',
                blockProps,
                el( 'h4', {}, 'VPG Locations by Service' ),
                el( SelectControl, {
                    label: 'Service',
                    value: attributes.serviceId,
                    options: serviceOptions,
                    onChange: function( value ) {
                        setAttributes( { serviceId: value } );
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
    window.wp.data,
    window.wp.blockEditor || ( window.wp.editor && window.wp.editor.useBlockProps ? window.wp.editor : null )
);
