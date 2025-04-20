(function(blocks, element, blockEditor, components) {
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var RangeControl = components.RangeControl;
    var ToggleControl = components.ToggleControl;
    var ServerSideRender = components.ServerSideRender;
    
    blocks.registerBlockType('social-bridge/likes-collage', {
        title: 'Social Likes Collage',
        icon: 'groups',
        category: 'widgets',
        attributes: {
            platform: {
                type: 'string',
                default: ''
            },
            maxUsers: {
                type: 'number',
                default: 8
            },
            avatarSize: {
                type: 'number',
                default: 48
            },
            showTotal: {
                type: 'boolean',
                default: true
            }
        },
        
        edit: function(props) {
            var attributes = props.attributes;
            
            // Prepare platform options
            var platformOptions = [
                { value: '', label: 'All Platforms' }
            ];
            
            if (typeof socialBridgePlatforms !== 'undefined') {
                socialBridgePlatforms.forEach(function(platform) {
                    platformOptions.push({
                        value: platform.id,
                        label: platform.name
                    });
                });
            }
            
            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: 'Settings', initialOpen: true },
                        el(SelectControl, {
                            label: 'Platform',
                            value: attributes.platform,
                            options: platformOptions,
                            onChange: function(value) {
                                props.setAttributes({ platform: value });
                            }
                        }),
                        el(RangeControl, {
                            label: 'Maximum Users',
                            value: attributes.maxUsers,
                            min: 1,
                            max: 50,
                            onChange: function(value) {
                                props.setAttributes({ maxUsers: value });
                            }
                        }),
                        el(RangeControl, {
                            label: 'Avatar Size',
                            value: attributes.avatarSize,
                            min: 16,
                            max: 128,
                            onChange: function(value) {
                                props.setAttributes({ avatarSize: value });
                            }
                        }),
                        el(ToggleControl, {
                            label: 'Show Total Count',
                            checked: attributes.showTotal,
                            onChange: function(value) {
                                props.setAttributes({ showTotal: value });
                            }
                        })
                    )
                ),
                el(ServerSideRender, {
                    block: 'social-bridge/likes-collage',
                    attributes: attributes
                })
            ];
        },
        
        save: function() {
            return null; // Dynamic block, server-side rendered
        }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components
);