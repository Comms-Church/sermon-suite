/**
 * Sermon Suite — Gutenberg Blocks
 * All blocks use ServerSideRender for live previews.
 */
( function( blocks, element, blockEditor, components, serverSideRender ) {
    var el          = element.createElement;
    var Fragment    = element.Fragment;
    var SSR         = serverSideRender;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody   = components.PanelBody;
    var SelectControl = components.SelectControl;
    var ToggleControl = components.ToggleControl;
    var RangeControl  = components.RangeControl;
    var TextControl   = components.TextControl;
    var Spinner       = components.Spinner;
    var Placeholder   = components.Placeholder;

    var data = window.sermonSuiteBlocks || { series: [], sermons: [] };

    var CATEGORY = 'sermon-suite';

    // Register block category
    if ( blocks.getCategories().findIndex( function(c){ return c.slug === CATEGORY; } ) === -1 ) {
        var existingCategories = blocks.getCategories();
        blocks.setCategories( [ { slug: CATEGORY, title: 'Sermon Suite', icon: 'video-alt3' } ].concat( existingCategories ) );
    }

    // ── Shared block icon ────────────────────────────────────────────────────
    function ssIcon( color ) {
        return el( 'svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: color || '#2563eb' },
            el( 'path', { d: 'M4 6h16M4 10h16M4 14h10M4 18h6' , stroke: color||'#2563eb', strokeWidth:2, strokeLinecap:'round', fill:'none' } ),
            el( 'circle', { cx: 18, cy: 16, r: 4, fill: color||'#2563eb' } ),
            el( 'path', { d: 'M16.5 16l2.5-1.5v3L16.5 16z', fill: '#fff' } )
        );
    }

    // ── Placeholder wrapper ──────────────────────────────────────────────────
    function ssPlaceholder( label, children ) {
        return el( Placeholder, {
            icon: ssIcon(),
            label: label,
            className: 'ss-block-placeholder',
        }, children );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BLOCK 1: Sermon Archive
    // ══════════════════════════════════════════════════════════════════════════
    blocks.registerBlockType( 'sermon-suite/archive', {
        title:       'Sermon Archive',
        description: 'Series grid or list view with optional topic filter.',
        category:    CATEGORY,
        icon:        ssIcon(),
        keywords:    [ 'sermons', 'archive', 'series', 'messages' ],
        attributes: {
            layout:           { type: 'string',  default: 'grid' },
            columns:          { type: 'integer', default: 3 },
            showFilter:       { type: 'boolean', default: true },
            featuredFirst:    { type: 'boolean', default: true },
            count:            { type: 'integer', default: -1 },
            sermonsPerSeries: { type: 'integer', default: 5 },
            category:         { type: 'string',  default: '' },
        },
        edit: function( props ) {
            var attrs = props.attributes;
            var set   = props.setAttributes;
            var isGrid = attrs.layout === 'grid';

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: 'Layout', initialOpen: true },
                        el( SelectControl, {
                            label: 'Display Style',
                            value: attrs.layout,
                            options: [
                                { value: 'grid', label: 'Grid — series cards' },
                                { value: 'list', label: 'List — series blocks with sermon rows' },
                            ],
                            onChange: function(v){ set({ layout: v }); }
                        }),
                        isGrid && el( RangeControl, {
                            label: 'Columns',
                            value: attrs.columns,
                            min: 1, max: 4,
                            onChange: function(v){ set({ columns: v }); }
                        }),
                        ! isGrid && el( RangeControl, {
                            label: 'Sermons shown per series',
                            value: attrs.sermonsPerSeries,
                            min: 1, max: 20,
                            onChange: function(v){ set({ sermonsPerSeries: v }); }
                        })
                    ),
                    el( PanelBody, { title: 'Filtering & Sorting', initialOpen: false },
                        el( SelectControl, {
                            label: 'Filter by Category',
                            value: attrs.category,
                            options: [ { value: '', label: 'All categories' } ].concat( data.categories || [] ),
                            onChange: function(v){ set({ category: v }); }
                        }),
                        el( ToggleControl, {
                            label: 'Show topic filter bar',
                            checked: attrs.showFilter,
                            onChange: function(v){ set({ showFilter: v }); }
                        }),
                        el( ToggleControl, {
                            label: 'Featured series first',
                            checked: attrs.featuredFirst,
                            onChange: function(v){ set({ featuredFirst: v }); }
                        }),
                        el( SelectControl, {
                            label: 'Max series to show',
                            value: String(attrs.count),
                            options: [
                                { value: '-1', label: 'All' },
                                { value: '3',  label: '3' },
                                { value: '6',  label: '6' },
                                { value: '9',  label: '9' },
                                { value: '12', label: '12' },
                            ],
                            onChange: function(v){ set({ count: parseInt(v) }); }
                        })
                    )
                ),
                el( SSR, { block: 'sermon-suite/archive', attributes: attrs } )
            );
        },
        save: function() { return null; } // server-side render
    });

    // ══════════════════════════════════════════════════════════════════════════
    // BLOCK 2: Latest Message Hero
    // ══════════════════════════════════════════════════════════════════════════
    blocks.registerBlockType( 'sermon-suite/hero', {
        title:       'Latest Message Hero',
        description: 'Full-width banner showing your most recent sermon — updates automatically.',
        category:    CATEGORY,
        icon:        ssIcon(),
        keywords:    [ 'hero', 'latest', 'sermon', 'banner', 'featured' ],
        attributes: {
            label: { type: 'string', default: 'Latest Message' },
        },
        edit: function( props ) {
            var attrs = props.attributes;
            var set   = props.setAttributes;
            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: 'Hero Settings', initialOpen: true },
                        el( TextControl, {
                            label: 'Badge Label',
                            help:  'Text shown above the sermon title (e.g. "Latest Message", "This Week")',
                            value: attrs.label,
                            onChange: function(v){ set({ label: v }); }
                        })
                    )
                ),
                el( SSR, { block: 'sermon-suite/hero', attributes: attrs } )
            );
        },
        save: function() { return null; }
    });

    // ══════════════════════════════════════════════════════════════════════════
    // BLOCK 3: Series Grid
    // ══════════════════════════════════════════════════════════════════════════
    blocks.registerBlockType( 'sermon-suite/series-grid', {
        title:       'Series Grid',
        description: 'A grid of series cards — use on homepage, landing pages, or anywhere.',
        category:    CATEGORY,
        icon:        ssIcon(),
        keywords:    [ 'series', 'grid', 'cards', 'sermons' ],
        attributes: {
            columns:  { type: 'integer', default: 3 },
            count:    { type: 'integer', default: -1 },
            featured: { type: 'boolean', default: false },
            category: { type: 'string',  default: '' },
        },
        edit: function( props ) {
            var attrs = props.attributes;
            var set   = props.setAttributes;
            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: 'Grid Settings', initialOpen: true },
                        el( SelectControl, {
                            label: 'Filter by Category',
                            value: attrs.category,
                            options: [ { value: '', label: 'All categories' } ].concat( data.categories || [] ),
                            onChange: function(v){ set({ category: v }); }
                        }),
                        el( RangeControl, {
                            label: 'Columns',
                            value: attrs.columns,
                            min: 1, max: 4,
                            onChange: function(v){ set({ columns: v }); }
                        }),
                        el( SelectControl, {
                            label: 'Number of Series',
                            value: String(attrs.count),
                            options: [
                                { value: '-1', label: 'All series' },
                                { value: '3',  label: '3' },
                                { value: '6',  label: '6' },
                                { value: '9',  label: '9' },
                            ],
                            onChange: function(v){ set({ count: parseInt(v) }); }
                        }),
                        el( ToggleControl, {
                            label: 'Featured series only',
                            checked: attrs.featured,
                            onChange: function(v){ set({ featured: v }); }
                        })
                    )
                ),
                el( SSR, { block: 'sermon-suite/series-grid', attributes: attrs } )
            );
        },
        save: function() { return null; }
    });

    // ══════════════════════════════════════════════════════════════════════════
    // BLOCK 4: Sermon Player
    // ══════════════════════════════════════════════════════════════════════════
    blocks.registerBlockType( 'sermon-suite/player', {
        title:       'Sermon Player',
        description: 'Embed a single sermon with video, scripture, and download links.',
        category:    CATEGORY,
        icon:        ssIcon(),
        keywords:    [ 'sermon', 'player', 'video', 'embed', 'youtube' ],
        attributes: {
            sermonId: { type: 'integer', default: 0 },
        },
        edit: function( props ) {
            var attrs   = props.attributes;
            var set     = props.setAttributes;
            var options = [ { value: '0', label: '— Select a sermon —' } ]
                .concat( data.sermons.map( function(s){ return { value: s.value, label: s.label }; } ) );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: 'Sermon', initialOpen: true },
                        el( SelectControl, {
                            label: 'Select Sermon',
                            value: String(attrs.sermonId),
                            options: options,
                            onChange: function(v){ set({ sermonId: parseInt(v) }); }
                        })
                    )
                ),
                attrs.sermonId
                    ? el( SSR, { block: 'sermon-suite/player', attributes: attrs } )
                    : ssPlaceholder( 'Sermon Player', el( 'p', { style: { color: '#666', fontSize: '0.88rem' } }, 'Select a sermon in the block settings panel →' ) )
            );
        },
        save: function() { return null; }
    });

    // ══════════════════════════════════════════════════════════════════════════
    // BLOCK 5: Related Sermons
    // ══════════════════════════════════════════════════════════════════════════
    blocks.registerBlockType( 'sermon-suite/related', {
        title:       'Related Sermons',
        description: 'Shows "More from this series" and "More on this topic" grids.',
        category:    CATEGORY,
        icon:        ssIcon(),
        keywords:    [ 'related', 'sermons', 'more', 'series', 'topic' ],
        attributes: {
            sermonId: { type: 'integer', default: 0 },
            count:    { type: 'integer', default: 4 },
        },
        edit: function( props ) {
            var attrs   = props.attributes;
            var set     = props.setAttributes;
            var options = [ { value: '0', label: '— Select a sermon —' } ]
                .concat( data.sermons.map( function(s){ return { value: s.value, label: s.label }; } ) );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: 'Settings', initialOpen: true },
                        el( SelectControl, {
                            label: 'Sermon',
                            value: String(attrs.sermonId),
                            options: options,
                            onChange: function(v){ set({ sermonId: parseInt(v) }); }
                        }),
                        el( RangeControl, {
                            label: 'Cards per section',
                            value: attrs.count,
                            min: 2, max: 8,
                            onChange: function(v){ set({ count: v }); }
                        })
                    )
                ),
                attrs.sermonId
                    ? el( SSR, { block: 'sermon-suite/related', attributes: attrs } )
                    : ssPlaceholder( 'Related Sermons', el( 'p', { style: { color: '#666', fontSize: '0.88rem' } }, 'Select a sermon in the block settings panel →' ) )
            );
        },
        save: function() { return null; }
    });

    // ══════════════════════════════════════════════════════════════════════════
    // BLOCK 6: Browse by Topic (directory)
    // ══════════════════════════════════════════════════════════════════════════
    blocks.registerBlockType( 'sermon-suite/topics', {
        title:       'Browse by Topic',
        description: 'A directory grid of all topics. Visitors click a topic to see every sermon on it.',
        category:    CATEGORY,
        icon:        ssIcon(),
        keywords:    [ 'topics', 'browse', 'directory', 'tags', 'subjects' ],
        attributes: {
            columns:   { type: 'integer', default: 4 },
            minCount:  { type: 'integer', default: 1 },
            showCount: { type: 'boolean', default: true },
            orderby:   { type: 'string',  default: 'count' },
        },
        edit: function( props ) {
            var attrs = props.attributes;
            var set   = props.setAttributes;
            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: 'Directory Settings', initialOpen: true },
                        el( RangeControl, {
                            label: 'Columns',
                            value: attrs.columns,
                            min: 2, max: 6,
                            onChange: function(v){ set({ columns: v }); }
                        }),
                        el( SelectControl, {
                            label: 'Order by',
                            value: attrs.orderby,
                            options: [
                                { value: 'count', label: 'Most sermons first' },
                                { value: 'name',  label: 'Alphabetical' },
                            ],
                            onChange: function(v){ set({ orderby: v }); }
                        }),
                        el( RangeControl, {
                            label: 'Minimum sermons to show',
                            value: attrs.minCount,
                            min: 1, max: 10,
                            onChange: function(v){ set({ minCount: v }); }
                        }),
                        el( ToggleControl, {
                            label: 'Show sermon count on each topic',
                            checked: attrs.showCount,
                            onChange: function(v){ set({ showCount: v }); }
                        })
                    )
                ),
                el( SSR, { block: 'sermon-suite/topics', attributes: attrs } )
            );
        },
        save: function() { return null; }
    });

}(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.serverSideRender
) );
