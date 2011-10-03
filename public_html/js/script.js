$(function() {
  $('td a.link').live('click', function(event) {
    event.preventDefault();
    window.open(this.href);
  });
  
  $('td a.delete').live('click', function(event) {
    event.preventDefault();
    if (confirm('Shure you want to delete this link ?')) {
      var $this = $(this);
      $.post(this.href, function(result) {
        if (result.status == 200) {
          $this.parent().parent().fadeOut('fast');
        }
      });
    }
  });  
});