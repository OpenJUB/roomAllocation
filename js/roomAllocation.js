/***************************************************************************\
    This file is part of RoomAllocation.

    RoomAllocation is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    RoomAllocation is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with RoomAllocation.  If not, see <http://www.gnu.org/licenses/>.
\***************************************************************************/

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
  var $freshman;
  var $loading;
  
  $(function(){
    alter_jquery_ui();
    set_variables();
    init_roommate_search();
    init_freshman_toggle();
    init_select_rooms();
    add_floorplan_events();
    register_global_ajax_handlers();
    setup_college_chooser();
    setup_tutorial(window);
    bulk_events();
    // refresh after 20 minutes so you don't get a session timeout
    setTimeout( RPC.reload, 20 * 60 * 1000 );
  });

  var setup_tutorial = function(exports) {
    
    exports.tutorial_running = false;

    var tourdata = [
      {
        html: "Welcome to the College Choice phase. <br /> This is a step by step tutorial. <br />You can use the commands bellow every message to <b>stop</b>/<b>pause</b>/<b>rewind</b> and <b>fast forward</b> the tutorial.<br /> Enjoy!",
        overlayOpacity: 0.8
       },
       {
        html: "Choose any college you want to move.",
        element: $('.college-choice:nth(1)'),
        overlayOpacity: 0.8,
        expose: true,
        position: 'e'
       },
       {
        html: "Drag the college into a different position.",
        element: $('.college-choice:nth(2)'),
        overlayOpacity: 0.8,
        expose: true,
        position: 'e',

       },
       {
        html: "The first place shows your favorite college for next semester.",
        element: $('.college-choice:nth(0)'),
        overlayOpacity: 0.8,
        expose: true,
        position: 'e',
       },
       {
        html: "The last place your least favorite college.",
        element: $('.college-choice:nth(3)'),
        overlayOpacity: 0.8,
        expose: true,
        position: 'e',
       },
       {
        html: 'If you are not going to be on campus for a semester or going on exchange, make sure to tick the box',
        element: $('#exchange'),
        overlayOpacity: 0.8,
        position: 'e',
        expose: true
       },
       {
        html: 'Also if you are considering choosing a room on a quiet floor, tick here. This is by no means binding, it is just so the College Masters have an idea on the overall preferences',
        element: $('#quiet_zone'),
        overlayOpacity: 0.8,
        position: 'e',
        expose: true
       },
       {
        html: "After each action your preferences are automatically shown and a confirmation message for storing will be shown on top.",
        overlayOpacity: 0.8,
        onBeforeShow: function(element) {
          message("info", "College prefences updated!")
        },
       },
       {
          html: 'When in doubt, you can always re-start the tour from here',
          element: $('#beginTour'),
          overlayOpacity: 0.8,
          position: 'w',
          expose: true
        }
    ];

    var key_name = window.location.origin + window.location.pathname;
    var tutorial_opts = {
      axis: 'y',  // use only one axis prevent flickring on iOS devices
      autostart: true
    };
    if (window.localStorage) {
      if (window.localStorage[key_name]) {
        var storage = JSON.parse(window.localStorage[key_name]);
        if (storage.tutorial) {
          tutorial_opts.autostart = false;
        }
      }
      window.localStorage[key_name] = JSON.stringify({tutorial:true});
    }

    var tour = jTour(
      tourdata,
      Object.create(tutorial_opts, {
        onStart: function (current) {
          $("#college_choices_sort").sortable( "disable" );
        },
        onStop: function (current) {
          $("#college_choices_sort").sortable( "enable" );
        }
      })
    );

    exports.tutorial = tour;

    $('#beginTour').bind('click', function(){
      window.tutorial.start();
    });
  }

  var setup_college_chooser = function() {
    function evalCollegeChoice (e, ui) {
        if (window.tutorial_running) {
          window.tutorial.next();
          console.log(ui);
        }
        var choices = $("#college_choices_sort").sortable("toArray");
        for(var i = 0; i < choices.length; i++) {
          choices[i] = choices[i].substr(7);
        }
        var exchange = $("#exchange_checkbox").is(":checked") ? 1 : 0;
        var quiet = $("#quiet_zone_checkbox").is(":checked") ? 1 : 0;
        $.get(ajax_file, { 
          'action' : 'setCollegeChoices',
          'choices' : choices,
          'exchange' : exchange,
          'quiet' : quiet
        }, function(data){
          // TODO: implement handle response
        });
    }
    $("#college_choices_sort").sortable({
      placeholder: "ui-state-highlight",
      update: evalCollegeChoice,
      change: function college_sort (event, ui) {
        ui.item.siblings('.college-choice').each(function () {
          $(this).find('.number').html(
            $(this).prevAll('li').not(ui.item).length + 1
          );
        });
        ui.item.find('.number').html(
          ui.placeholder.prevAll('.college-choice').not(ui.item).length + 1
        );
      }
    }); 
    $("#college_choices_sort").disableSelection();
    $("#exchange_checkbox").on("change", evalCollegeChoice);
    $("#quiet_zone_checkbox").on("change", evalCollegeChoice);
  }

  var bulk_events = function(){
    var checkbox_selector = '.college-additional-options input[type="checkbox"]';
    $(document).on('change', checkbox_selector, function () {
      if ($(this).attr('checked') === 'checked') {
        $(this).parent().addClass('checked');
      } else {
        $(this).parent().removeClass('checked');
      }
    });
    $(checkbox_selector+':checked').parent().addClass('checked');

    var $random_password_input = jq_element('input').attr('type', 'text');
    var has_seen_info_message = false;
    $('#random-password').on('click', function () {
      var random_password_info_message = function random_password_info_message () {
        message('info', 'A random password is another way you can login. You can choose for it to be sent to your jacobs email address and you can log in using your campusnet credentials OR with your campusnet username and the random password sent via email.<br />To get your random password, please consider donating a kidney .... just kidding :). Just put your campusnet username in the log-in box and press this button again.', 30 * 1000);
      }
      if (has_seen_info_message) {
        var account = $('input[name="username"]').val();
        $.get(ajax_file, {
          action: 'send_random_password',
          account: account
        }, function (response) {
          if (!response || !response.result) {
            if (!response.error) {
              message('error', 'Invalid username or something went wrong on the server');
            }
            return;
          }
          message('success', 'Mail sent to <b>'+response.email+'</b>, check your inbox');
        });
      } else {
        random_password_info_message();
      }
      has_seen_info_message = true;
    });

    $('.message .close').live('click.close', function(){
      var $msg = $(this).parent();
      $msg.fadeOut( 600, function(){ $msg.remove(); } );
    });
  }
  
  var init_select_rooms = function(){
    
    $('#select-rooms').bind('submit.selectRooms', function(e){
      var variables = {
        action  : 'selectRooms'
      };
      $(this).find('select').each(function(){
        variables[$(this).attr('name')] = $(this).val();
      });
      $.get( ajax_file, variables);
      return false;
    });
    
  };
  
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
    $freshman     = $('#toggle_freshman');
    
    $loading      = jq_element('img');
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
          var elem = jq_element('div').html(data.result).unwrap();
          $('#requests-sent').find('.none').slideUp().end().append( elem );
          elem.hide().slideDown();
        }
      });
      return false;
    });
  };
  
  var init_freshman_toggle = function(){
    if( $freshman.length > 0 ){
      $freshman.bind('click', function(){
        if( $(this).attr('checked') == 'checked' )
          $.get( ajax_file, {action:'addFreshman'} );
        else
          $.get( ajax_file, {action:'removeFreshman'} );
      });
    }
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
    
    var $selection          = jq_element('div');
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
          var choices = $('.room-choices [name="choice[]"]')
                          .map(function(i,v){ return $(this).val(); })
                          .get();
          $.get( ajax_file, {
            action  : 'chooseRooms',
            choices : choices
          });
        });
    };
    
    function create_selection(){
      $rooms = $('.room:not(.taken,.disabled)');
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
            $selection.dialog('close');
            $current_apartment  = $();
            current_choice      = null;
          } else {
            $selection.dialog('open');
          }
        });

      $selection.dialog({
        modal     : true,
        title     : 'Which option should it be',
        show      : 'slide',
        autoOpen  : false,
        width     : 350,
        open      : function( e, ui ){
          $selection
            .find('input')
            .each(function(){
              var val = $('#input-'+$(this).attr('id')).val();
              var val = val != '' ? val : no_choice;
              $(this).val( val );
            });
        }
      });
      
      $rooms.bind('click.showDialog', function(){
        $selection.dialog('open');
      });
    }
  })();
  
})( jQuery );

var messages = $();
var message_timeout;
function message (type, message, timeout) {
  timeout = timeout || 5000 + message.length * 20;
  var msg = messages.filter('.'+type);
  if( msg.length > 0 ){
    var container = msg.parent();
    var clone     = msg.clone();
    clone
      .appendTo( container )
      .hide()
      .fadeIn( 800 )
      .find('.content')
      .html( message );
    setTimeout(function(){
      clone.slideUp();
    }, timeout);
    container[0].scrollTop = container[0].scrollHeight;
  } else {
    console.warn( 'Unknown message type', arguments );
  }
}

function jq_element (type) {
  return $(document.createElement(type));
}