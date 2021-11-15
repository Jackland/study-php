summernote.autoInit = false;
$(document).ready(function() {
  $('[data-toggle=\'summernote\']').each(function () {
    var _this = $(this);
    summernote.init(_this, {
      buttons: {
        // Override summernotes image manager
        image: function() {
          var ui = $.summernote.ui;

          // create button
          var button = ui.button({
            contents: '<i class="note-icon-picture" />',
            tooltip: $.summernote.lang[$.summernote.options.lang].image.image,
            click: function () {
              $('#modal-image').remove();

              $.ajax({
                url: 'index.php?route=common/filemanager&user_token=' + getURLVar('user_token'),
                dataType: 'html',
                beforeSend: function() {
                  $('#button-image i').replaceWith('<i class="fa fa-circle-o-notch fa-spin"></i>');
                  $('#button-image').prop('disabled', true);
                },
                complete: function() {
                  $('#button-image i').replaceWith('<i class="fa fa-upload"></i>');
                  $('#button-image').prop('disabled', false);
                },
                success: function(html) {
                  $('body').append('<div id="modal-image" class="modal">' + html + '</div>');

                  $('#modal-image').modal('show');

                  $('#modal-image').delegate('a.thumbnail', 'click', function(e) {
                    e.preventDefault();

                    _this.summernote('insertImage', $(this).attr('href'));

                    $('#modal-image').modal('hide');
                  });
                }
              });
            }
          });
          return button.render();
        }
      },
      callbacks: {
        onInit: function () {
          update2Input(_this)
        },
        onChange: function () {
          update2Input(_this)
        }
      }
    })
  })
  function update2Input(summernoteEl) {
    var inputName = summernoteEl.data('input-name');
    if (!inputName) {
      return;
    }
    $('input[name="'+inputName+'"]').val(summernoteEl.summernote('code'))
  }
});