console = console || {log:function(){}, warn:function(){}, error:function(){}};

var MAX_ROOMS_CHOICES = 9;
var ajax_file         = 'ajax.php';

var sendResponse;

/**
 * contains all available remote-procedural calls
 *  a.k.a. the interface available to the backend
 */
var RPC = {
  reload  : function(){ window.location.reload(); },
  updatePoints  : function(){
    
  }
};

(function($){
  
  var $searchBox;
  var $search;
  var $eid;
  var $addRoommate;
  var $loading;
  var messages = $();
  var message_timeout;
  
  $(function(){
    alter_jquery_ui();
    set_variables();
    init_roommate_search();
    add_floorplan_events();
    register_global_ajax_handlers();
    // refresh after 20 minutes so you don't get a session timeout
    setTimeout( function(){ window.location.reload() }, 20 * 60 * 1000 );
  });
  
  var register_global_ajax_handlers = (function(){
    
    return function(){
      $('body').ajaxSuccess(function( e, xhr, settings, json ){
        if( $.isPlainObject( json ) ){
          handle_rpc( json );
          handle_messages( json );
          handle_roommates( json );
          handle_points( json );
        }
      });
    };
    
    function handle_points( json ){
      if( json.points ){
        $('#total-points').html( json.points );
        delete json.points;
      }
    };
    
    function handle_roommates( json ){
      if( $.isArray(json.roommates) ){
        var $cr = $('#current-roommates');
        if( json.roommates.length > 0 ){
          $cr.append( json.roommates.join("\n") );
          $cr.find('.none').slideUp();
        } else {
          $cr.find('.none').slideDown();
        }
        delete json.roommates;
      }
    };
    
    function handle_messages( json ){
      var types = [ 'error', 'warning', 'info', 'success' ];
      for( var i in types ){
        if( json[types[i]] ){
          var value = json[types[i]];
          if( $.isArray( value ) ){
            value = value.join('<br />');
          }
          message( types[i], value );
          delete json[types[i]];
          break;
        }
      }
    };
    
    function handle_rpc( json ){
      if( json.rpc ){
        eval( json.rpc );
        delete json.rpc;
      }
    };
    
  })();
  
  var alter_jquery_ui = function(){
    /* allows us to pass in HTML tags to autocomplete. Without this they get escaped */
    $[ "ui" ][ "autocomplete" ].prototype["_renderItem"] = function( ul, item ) {
      return $( "<li></li>" ) 
        .data( "item.autocomplete", item )
        .append( $( "<a></a>" ).html( item.label ) )
        .appendTo( ul );
    };
  };
  
  var set_variables = function(){
    $searchBox    = $('#searchBox');
    $search       = $('#search');
    $eid          = $('#roommate-eid');
    $addRoommate  = $('#addRoommate');
    
    $loading      = $(document.createElement('img'));
    $loading
      .insertBefore( $search )
      .attr({
        'src'       : 'images/ajax.gif',
        'height'    : 22
      })
      .css({
        position  : 'absolute',
        top       : 2,
        right     : 7
      }).hide();
      
    messages = $('#message-info,#message-error,#message-warning,#message-success');
  };
  
  var init_roommate_search = function(){
    $search
      .autocomplete({
        autofocus : true,
        minLength : 2,
        delay     : 200,
        source    : function( request, response ){
          $.get( ajax_file, {
            action  : 'autoComplete',
            str     : request.term
          }, function( data ){
            response($.map( data, function( item ){
              return {
                label : item.fname+' '+item.lname,
                value : item.fname+' '+item.lname,
                full  : item
              }
            }));
          });
        }
      });

    // Override default select method for the autocomplete to prevent the menu from closing
    if( $search.data("autocomplete") ){
      $search.data("autocomplete").menu.options.selected = function(event, data) {
        $search.focus();
        $search.autocomplete('close');
        $eid.val( data.item.data('item.autocomplete').full.eid );
        return false;
      };
    }
    
    $searchBox.bind('submit.addRoommate', function(){
      $addRoommate.hide();
      $loading.show();
      $.get( ajax_file, {
        action  : 'addRoommate',
        eid     : $eid.val()
      }, function( data ){
        $loading.hide();
        $addRoommate.show();
        if( data.result ){
          var elem = $(document.createElement('div')).html(data.result).unwrap();
          $('#requests-sent').find('.none').slideUp().end().append( elem );
          elem.hide().slideDown();
        }
      });
      return false;
    });
  };
  
  sendResponse = function( type, eid, msg ){
    if( ['requestReceived', 'requestSent'].indexOf(type) == -1 ){
      console.warn( 'Unknown type in sendResponse', arguments );
      return false;
    }
    $.get( ajax_file, {
      action  : type,
      eid     : eid,
      msg     : msg
    }, function( data ){
      if( data.result ){
        var $face = $('#face-eid-'+eid);
        if( $face.siblings(':visible').length == 0 ){
          $face.parent().find('.none').slideDown();
        }
        $face.fadeOut(800);
      }
    });
  }

  var add_floorplan_events = (function(){
    
    var $selection          = $(document.createElement('div'));
    var $current_apartment  = $();
    var $rooms              = $();
    var current_choice      = null;
    var no_choice           = '   ';
    var close_timeout       = null;
    
    return function(){
      create_selection();
      $rooms
        .bind('mouseover mouseout', function(){
          var $rooms = get_apartment( $(this) );
          $rooms.toggleClass('selected');
        })
        .bind('click.selectRoom', function(){
          $current_apartment = get_apartment( $(this) );
          current_choice = $current_apartment
                            .map(function(i,v){ return v.id.slice(5); })
                            .get()
                            .join(',');
        });
      $('#choose_rooms')
        .bind('click.chooseRooms', function(){
          var choices = $('.room-choices input[name="choice[]"]')
                          .map(function(i,v){ return $(this).val(); })
                          .get();
          $.get( ajax_file, {
            action  : 'chooseRooms',
            choices : choices
          });
        });
    };
    
    function create_selection(){
      $rooms = $('.room');
      var h = '';
      for( var i=0; i<MAX_ROOMS_CHOICES; ++i ){
        h += '<label class="choice">\
                <span class="title">Option '+(i+1)+'</span>\
                <input type="button" value="'+no_choice+'" id="room-choice-'+i+'" />\
              </label>';
      }
      $selection
        .attr( 'id',  'apartment-selection' )
        .html( h )
        //.appendTo( 'body' )
        .find('.choice input')
        .bind('click.setChoice', function(){
          if( current_choice ){
            if( $(this).val() !== no_choice ){
              var old_apartment = $(this)
                                      .val()
                                      .split(',')
                                      .map(function(v){return '#room-'+v;})
                                      .join(',');
              $(old_apartment).removeClass('chosen');
            }
            $current_apartment.addClass( 'chosen' );
            $(this).val( current_choice );
            $('#input-'+$(this).attr('id')).val( current_choice );
            $rooms.qtip('toggle', false);
            $current_apartment  = $();
            current_choice      = null;
          } else {
            $rooms.qtip('toggle', false);
          }
        });

      $rooms.qtip({
        content   : {
          text  : $selection,
          title: {
            text    : 'Seelect which option you want this apartment to be',
            button  : true
          }
        },
        position: {
          my      : 'center', // ...at the center of the viewport
          at      : 'center',
          target  : $(window)
        },
        show: {
          event : 'click', 
          solo  : true, 
          modal : true
        },
        hide: false,
        style: {
          classes : 'ui-tooltip-light ui-tooltip-rounded'
        },
        events : {
          show : function( event, api ){
            var target = $(event.originalEvent.target);
            $selection
              .find('input')
              .each(function(){
                var val = $('#input-'+$(this).attr('id')).val();
                var val = val != '' ? val : no_choice;
                $(this).val( val );
              });
          }
        }
      });
    }
  })();
  
  function message( type, message ){
    var msg = messages.filter('.'+type);
    if( msg.length > 0 ){
      var container = msg.parent();
      var clone     = msg.clone();
      clone
        .appendTo( container )
        .hide()
        .fadeIn( 800 )
        .find('.content')
        .html( message )
        .data( 'timeout', setTimeout(clear_message(clone), 10 * 1000) )
        .bind('click.clear', function(){
          clearTimeout( clone.data('timeout') );
          clear_message( clone )();
        });
      container[0].scrollTop = container[0].scrollHeight;
      /*
      if( container.height() > 200 ){
        var toHide = container.find('.message:visible').first();
        clearTimeout( toHide.data('timeout') );
        toHide.hide();
      }
      */
    } else {
      console.warn( 'Unknown message type', arguments );
    }
    
    function clear_message( clone, speed ){ 
      speed = speed || 800;
      return function(){
        clone.fadeOut(speed, function(){ clone.remove(); });
      }
    }
  }
  
})( jQuery );