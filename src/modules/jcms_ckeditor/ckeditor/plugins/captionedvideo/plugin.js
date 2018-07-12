( function() {
	'use strict';

	CKEDITOR.plugins.add( 'captionedvideo', {
		icons: 'captionedvideo',
		hidpi: true,
		requires: 'embedbase',
    
    		onLoad: function() {
			this.registerOembedTag();
		},
    
         init: function( editor ) {
			var widgetDefinition = CKEDITOR.plugins.embedBase.createWidgetBaseDefinition( editor ),
				origInit = widgetDefinition.init;

			CKEDITOR.dialog.add( 'captionedVideo', this.path + 'dialogs/captionedvideo.js' );
      
             CKEDITOR.tools.extend( widgetDefinition, {
				// Use a dialog exposed by the embedbase plugin.
				dialog: 'captionedVideo',
				button: 'Insert Captioned Video',
				allowedContent: 'figure(video,no-caption,with-caption),figcaption,oembed',
				requiredContent: 'oembed',
				styleableElements: 'oembed',
				// Share config with the embed plugin.
				providerUrl: new CKEDITOR.template(
					editor.config.embed_provider ||
					'//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}'
				),
                 template: '<figure class="video"><oembed>{url}</oembed><figcaption>{caption}</figcaption></figure>',
                 editables: {
                   caption: {
                     selector: 'figcaption'
                   }
                 },
                 defaults: {
                   caption: 'Default Caption',
                   url: ''
                 },
                         
                 init: function() {
                   	var that = this;

					origInit.call( this );

					// Need to wait for #ready with the initial content loading, because on #init there's no data yet.
					this.once( 'ready', function() {
						// When widget is created using dialog, the dialog's code will handle loading the content
						// (because it handles success and error), so do load the content only when loading data.
						if ( this.data.loadOnReady ) {
							this.loadContent( this.data.url, {
								callback: function() {
									// Do not load the content again on widget's next initialization (e.g. after undo or paste).
									// Plus, this is a small trick that we change loadOnReady now, inside the callback.
									// It guarantees that if the content was not loaded (an error occurred or someone
									// undid/copied sth to fast) the content will be loaded on the next initialization.
                                        //that.setData( 'caption', this.data.caption );
									that.setData( 'loadOnReady', false );
									editor.fire( 'updateSnapshot' );
								}
							} );
						}
					} );
                 },
                 
                 upcast: function( element, data ) {
                   	if ( element.name != 'figure' || element.classes.indexOf('video') < 0 ) {
                        return;
                      }

                      var text, div, loadVideo = false;
                      for (var i = 0; i < element.children.length; i++) {
                        if (element.children[i].name == 'oembed') {
                          text = element.children[i].children[0];
                          if ( text && text.type == CKEDITOR.NODE_TEXT && text.value ) {
                            data.url = text.value;
                            loadVideo = true;
                          }
                        }
                        else if (element.children[i].name == 'figcaption') {
                          text = element.children[i].children[0];
                          if ( text && text.type == CKEDITOR.NODE_TEXT && text.value ) {
                            data.caption = text.value;
                          }
                        }
                      }

                      if ( loadVideo ) {
                        data.loadOnReady = true;
                        element.attributes['class'] = element.classes.join(' ');
                        return true;
                      }
                 },
                 
                 downcast: function( element ) {
                      var ret = new CKEDITOR.htmlParser.element( 'figure' );
                      var embed = new CKEDITOR.htmlParser.element( 'oembed' );
                      embed.add( new CKEDITOR.htmlParser.text( this.data.url ) );
                      var caption = new CKEDITOR.htmlParser.element( 'figcaption' );
                      var txt = '';
                      if (this.editables.caption) {
                        txt = this.editables.caption.$.innerText;
                      }
                      caption.add( new CKEDITOR.htmlParser.text( txt ) );
                      ret.add(embed);
                      ret.add(caption);
                      if ( element.attributes[ 'class' ] ) {
				       ret.attributes[ 'class' ] = element.attributes[ 'class' ];
					}
					return ret;       
                 },
                 
                 _setContent: function( url, content ) {
                   this.setData( 'url', url );
                   for (var i = 0; i < this.element.$.childNodes.length; i++) {
                     if (this.element.$.childNodes[i].localName == 'oembed') {
                       this.element.$.childNodes[i].outerHTML = content;
                     }
                   }
                 }
                 
             }, true );

			editor.widgets.add( 'captionedVideo', widgetDefinition );
		},
         
         // Extends CKEDITOR.dtd so editor accepts <oembed> tag.
		registerOembedTag: function() {
			var dtd = CKEDITOR.dtd,
				name;

			// The oembed tag may contain text only.
			dtd.oembed = { '#': 1 };

			// Register oembed tag as allowed child, in each tag that can contain a div.
			// It also registers the oembed tag in objects like $block, $blockLimit, etc.
			for ( name in dtd ) {
				if ( dtd[ name ].div ) {
					dtd[ name ].oembed = 1;
				}
			}
		}
    
	} );

} )();


