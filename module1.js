M.show_forum_panel = {
    init: function(Y, options) {
        this.Y = Y;
        this.spinnerpic = options.spinner;
        this.mid = options.mid;
        var showForumBtn  = Y.one('#showForum');
        showForumBtn.on('click', this.show_on_click);
    },

    show_on_click: function(e) {
        M.show_forum_panel.showforumpanel();
    },

    showforumpanel: function() {
        var Y = this.Y;
         var url = M.cfg.wwwroot + "/blocks/fn_marking/forum_view.php?id=" + this.mid;
        var div = Y.Node.create('</div><div/>');
        div.set("id", "forumiframecontainer");

        // Create iframe.
        var ifrm = Y.Node.create('<iframe frameborder="0"><iframe>');
        ifrm.set("src", url);
        ifrm.set("name", "forumiframe");
        ifrm.set("id", "forumiframe");
        ifrm.setStyle('width', '100%');
        ifrm.setStyle('height', '430px');
        ifrm.setStyle('display', 'none');
        div.append(ifrm);

         Y.on('contentready', function(){
                                ifrm.setStyle('display', 'block');
         }, '#forumiframe');

            var panel;
            panel = new Y.Panel({
                bodyContent : div,
                headerContent: 'Forum marking Area',
                width        : 950,
                height       : 500,
                zIndex       : 5,
                xy     : [300, -300],
                centered     : true,
                modal        : true,
                visible      : false,
                render       : true,
                scroll       : true,
                focusOn      : Y.one('#region-main-box'),
                buttons: [{value  : 'Close the panel',
                    section: 'footer',
                    action : function (e) {
                             e.preventDefault();
                             panel.hide();
                             window.location.reload();
                    }
                },
                                        {value  : 'Close the panel',
                                            section: 'header',
                                            action : function (e) {
                                                e.preventDefault();
                                                panel.hide();
                                                window.location.reload();
                                            }
                }]

                        });

            panel.show();

    }
}
