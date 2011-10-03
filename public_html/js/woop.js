/*
 * This file is part of Woop.
 *
 * (c) Ulrik Nielsen <ulrik@bellcom.dk>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

var woop = {
  version : '<?php echo $version_id  ?>',
  key : '<?php echo $key  ?>',

  // handles clicks on the "bookmarklet"
  click : function () {
    
    var el = document.getElementById('woop-click');
    if (el != undefined) {
      return this.alert("This page is already wOOp'ed.");
    }

    var title = document.title;
    var url = document.location.href;

    if (this.confirm(title, url)) {
      var secret = this.prompt('Please enter your wOOp! secret');
      var regex = /[a-z0-9]+/i;
      if (!regex.test(secret)) {
        this.alert('Invalid or empty secret.');
        return '';
      }

      var woop = document.createElement('script');
      woop.type = 'text/javascript';
      woop.id = 'woop-click';
      woop.async = true;
      woop.src = 'http://woop.bc/add/' + this.version + '/' + encodeURIComponent(this.key + '.' + secret) + '/?title=' + encodeURIComponent(title) + '&url=' + encodeURIComponent(url);

      var s = document.getElementsByTagName('script')[0];
      s.parentNode.insertBefore(woop, s);
    }
  },

  /**
   * confirm override - here for later rewrite
   * 
   * @param title string
   * @param url string
   */
  confirm : function(title, url) {
    var short_url = url;
    if (short_url.length > title.length) {
      short_url = url.substring(0, title.length - 3) + '...';
    }

    return confirm(this.t('Yup! Go ahead and add this page to my wOOp! account.\n\n"%title%"\n%short_url%', {
      '%title%' : title,
      '%short_url%' : short_url
    }));
  },
  
  /**
   * alert override - here for later rewrite
   * 
   * @param msg string
   * @return void
   */
  alert : function (msg) {
    alert(this.t(msg));
  },

  /**
   * prompt override - here for later rewrite
   * 
   * @param msg string
   * @return string
   */
  prompt : function (msg) {
    return prompt(this.t(msg));
  },

  /**
   * handle translations
   * the function handles substitution of placeholders in text.
   * 
   * @param key string
   * @param params object
   * @return string
   */
  t : function(key, params) {
    var msg = this.i18n[key];
    if (msg === undefined) {
      msg = key;
    }

    // replace substitutions if any
    if (params !== undefined) {
      for(var param in params) {
        msg = msg.replace(new RegExp(param, 'gm'), params[param]);
      }
    }
    return msg;
  },

  i18n : {
     // 'Yup! Go ahead and add this page to my wOOp! account.\n\n"%title%"\n%short_url%' : 'Jada! Tilføj endelig siden her til min wOOp! konto.\n\n"%title%"\n%short_url%',
     // 'Please enter your wOOp! secret' : 'Indtast venligst din wOOp! nøgle',
     // 'Invalid or empty secret.' : 'Ugyldig wOOp! nøgle',
     // "This page is already wOOp'ed" : "Du har allerede wOOp'ed denne side."
  }
};
woop.click()
