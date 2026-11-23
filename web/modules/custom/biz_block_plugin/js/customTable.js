
(function ($, Drupal, drupalSettings, once) {
//
// Pipelining function for DataTables. To be used to the `ajax` option of DataTables
//

$.fn.dataTable.pipeline = function ( opts ) {
    // Configuration options
    var conf = $.extend( {
        pages: 5,     // number of pages to cache
        url: '',      // script url
        data: null,   // function or object with parameters to send to the server
                      // matching how `ajax.data` works in DataTables
        method: 'GET' // Ajax HTTP method
    }, opts );

    // Private variables for storing the cache
    var cacheLower = -1;
    var cacheUpper = null;
    var cacheLastRequest = null;
    var cacheLastJson = null;

    return function ( request, drawCallback, settings ) {
        var ajax          = false;
        var requestStart  = request.start;
        var drawStart     = request.start;
        var requestLength = request.length;
        var requestEnd    = requestStart + requestLength;
        if ( settings.clearCache ) {
            // API requested that the cache be cleared
            ajax = true;
            settings.clearCache = false;
        }
        else if ( cacheLower < 0 || requestStart < cacheLower || requestEnd > cacheUpper ) {
            // outside cached data - need to make a request
            ajax = true;
        }
        else if ( JSON.stringify( request.order )   !== JSON.stringify( cacheLastRequest.order ) ||
                  JSON.stringify( request.columns ) !== JSON.stringify( cacheLastRequest.columns ) ||
                  JSON.stringify( request.search )  !== JSON.stringify( cacheLastRequest.search )
        ) {
            // properties changed (ordering, columns, searching)
            ajax = true;
        }

        // Store the request for checking next time around
        cacheLastRequest = $.extend( true, {}, request );

        if ( ajax ) {
            // Need data from the server
            if ( requestStart < cacheLower ) {
                requestStart = requestStart - (requestLength*(conf.pages-1));
 
                if ( requestStart < 0 ) {
                    requestStart = 0;
                }
            }

            cacheLower = requestStart;
            cacheUpper = requestStart + (requestLength * conf.pages);

            request.start = requestStart;
            request.length = requestLength*conf.pages;

            // Provide the same `data` options as DataTables.
            if ( typeof conf.data === 'function' ) {
                // As a function it is executed with the data object as an arg
                // for manipulation. If an object is returned, it is used as the
                // data object to submit
                var d = conf.data( request );
                if ( d ) {
                    $.extend( request, d );
                }
            }
            else if ( $.isPlainObject( conf.data ) ) {
                // As an object, the data given extends the default
                $.extend( request, conf.data );
            }

            return $.ajax( {
                "type":     conf.method,
                "url":      conf.url,
                "data":     request,
                "dataType": "json",
                "cache":    false,
                "cors": false,
                "crossDomain": true,
                "headers": {'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*'},
                "success":  function ( json ) {
                    json = JSON.parse(json);
                    cacheLastJson = $.extend(true, {}, json);
                    if ( cacheLower != drawStart ) {
                        json.data.splice( 0, drawStart-cacheLower );
                    }
                    if ( requestLength >= -1 ) {
                        json.data.splice( requestLength, json.data.length );
                    }
                    drawCallback( json );
                }
            } );
        }
        else {
            json = $.extend( true, {}, cacheLastJson );
            json.draw = request.draw; // Update the echo for each response
            json.data.splice( 0, requestStart-cacheLower );
            json.data.splice( requestLength, json.data.length );

            drawCallback(json);
        }
    }
};


// Register an API method that will empty the pipelined data, forcing an Ajax
// fetch on the next draw (i.e. `table.clearPipeline().draw()`)
$.fn.dataTable.Api.register( 'clearPipeline()', function () {
    return this.iterator( 'table', function ( settings ) {
        settings.clearCache = true;
    } );
} );


  var varsToPatch;
	var sid, webformId,flagToModify,date,count, status_version,current_count, current_language;

    Drupal.behaviors.customTable = {
        attach: function (context, settings) {
            current_language = settings.path.currentLanguage;
            var sort_column = parseInt(drupalSettings.biz_block_plugin.sort_column) || 0;
            var sort_order = drupalSettings.biz_block_plugin.sort_order || 'desc';

            if (!drupalSettings.biz_block_plugin || !drupalSettings.biz_block_plugin.id) {
              return;
            }

            if(drupalSettings.biz_block_plugin.url_api == ''){
              var tableId = '#' + drupalSettings.biz_block_plugin.id;
              var $table = $(context).find(tableId);

              if (!$table.length) {
                return;
              }
              $(once('datatable-init', $table, context)).each(function () {
              //$table.once('datatable-init').each(function () {
                var $currentTable = $(this);

                if ($.fn.DataTable.isDataTable(this)) {
                  $currentTable.DataTable().destroy();
                }

                $currentTable.DataTable({
                  pagingType: 'full_numbers',
                  searching: false,
                  order: [[ sort_column, sort_order ]]
                });

                $currentTable.off('order.dt page.dt');
                $currentTable.on('order.dt page.dt', function () {
                  addEvent2Confirm(context, settings);
                });

                addEvent2Confirm(context, settings);
              });
            }
            else{
              var tableId = '#' + drupalSettings.biz_block_plugin.id;
              var $table = $(context).find(tableId);

              if (!$table.length) {
                return;
              }
               $(once('datatable-init-server', $table, context)).each(function () {
              //$table.once('datatable-init-server').each(function () {
                var $currentTable = $(this);

                if (drupalSettings.biz_block_plugin.add_html_top !== '') {
                  $currentTable.closest('section').prepend(drupalSettings.biz_block_plugin.add_html_top);
                }

                if ($.fn.DataTable.isDataTable(this)) {
                  $currentTable.DataTable().destroy();
                }

                $currentTable.DataTable({
                  ordering: false,
                  pagingType: 'full_numbers',
                  processing: true,
                  serverSide: true,
                  searching: false,
                  ajax: $.fn.dataTable.pipeline({
                    url: drupalSettings.biz_block_plugin.url_api,
                    pages: 5
                  })
                });
              });
            }
            // $(context).find('#'+drupalSettings.biz_block_plugin.id+' tbody').once("compare-version").each(function () {
            //   $(this).on('click', '.compare-versions', function () {
            //         varsToGetRevision = $(this).children("div").attr("data");
            // 		    [date, sid, count, status_version,current_count] = varsToGetRevision.split('&');
            // 		    date = date.replaceAll("/", "-");
            // 		    if($("#compare-modal-"+date).length){
            //   		    $("#compare-modal-"+date).modal('show');
            // 		    }else{
            //   		    getVersions();
            //         }
            //     } );
            // });
            
             const blockId = settings.biz_block_plugin.id;
              const selector = '#' + blockId + ' tbody';
        
              once('compare-version', selector, context).forEach(function (tbody) {
        
                $(tbody).on('click', '.compare-versions', function (e) {
        
                  const varsToGetRevision = $(this)
                    .children('div')
                    .attr('data');
        
                  if (!varsToGetRevision) {
                    return;
                  }
        
                  let [date, sid, count, status_version, current_count] =
                    varsToGetRevision.split('&');
        
                  date = date.replace(/\//g, '-');
        
                  const modalId = '#compare-modal-' + date;
        
                  if ($(modalId).length) {
                    $(modalId).modal('show');
                  }
                  else {
                    getVersions(date, sid, count, status_version, current_count);
                  }
        
                });
        
              });

        }
    };
    function addEvent2Confirm(context, settings){
        setTimeout(function(){
            $(".delete-action, .accept-action").off();
        		$(".delete-action, .accept-action").on("click", function(e){
        		    varsToPatch = $(this).children("div").attr("data");
        		    [sid, webformId,flagToModify] = varsToPatch.split('&');
        		    $("#confirmModal").modal('show');
        		});

        		$("#modal-btn-yes").on("click", function(){
            		switch(flagToModify){
                		case "Active":
                            patchNode({ "frontend_status": flagToModify, "status": flagToModify, 'user_uid': settings.user.uid });
                		break;
                		default:
                            patchNode({ "deleted": true });
                		break;
            		}
        		});
        		$("#modal-btn-no").on("click", function(){
        		    $("#confirmModal").modal('hide');
        		});
        }, 100);
    }
    function patchNode(data) {
        $.ajax({
            url:    '/patch/' + webformId    + '/' + sid + '/' + JSON.stringify(data),
            method: 'PATCH',
            cors: false,
            headers: {'Content-Type': 'application/json'},
            success: function (data) {
    	        $("#confirmModal").modal('hide');
    	        location.reload(true);
            }
        });
    }
    function getVersions(date, sid, count, current_count) {

        // Make sure date is available.
        if (!date) {
          return;
        }
    
        $.ajax({
          url: '/' + current_language +
            '/get-version/' + sid +
            '/' + date +
            '/' + count +
            '/' + current_count,
    
          method: 'GET',
          headers: {
            'Content-Type': 'application/json'
          },
    
          success: function (data) {
            $('body').append(data);
    
            $('#compare-modal-' + date).modal('show');
          },
    
          error: function (xhr, status, error) {
            console.error('Error loading versions:', error);
          }
        });
    
      }
})(jQuery, Drupal, drupalSettings, once);