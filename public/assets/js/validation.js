/**
 * Sistema Universitario
 * public/assets/js/validation.js
 *
 * Validacion de formularios en el navegador (espejo de las reglas del
 * servidor). La autoridad final siempre es el servidor.
 */
(function () {
  'use strict';

  if (typeof window.APP === 'undefined') {
    return;
  }

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var PASSWORD_RE = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

  function setError(input, message) {
    var err = document.getElementById(input.id + '-error');
    var invalid = message !== '' && message != null;

    if (err) {
      err.textContent = message || '';
    }
    input.classList.toggle('is-invalid', invalid);
    input.setAttribute('aria-invalid', invalid ? 'true' : 'false');
  }

  function validateField(input) {
    var form = input.form;
    var mode = form ? form.getAttribute('data-validate') : '';
    var value = input.value.trim();
    var message = '';

    if (input.required && value === '') {
      message = 'Este campo es obligatorio.';
    } else if (input.type === 'email' && value !== '' && !EMAIL_RE.test(value)) {
      message = 'Ingresa un correo electronico valido.';
    } else if (input.name === 'password' && mode === 'reset' && value !== '' && !PASSWORD_RE.test(value)) {
      message = 'Minimo 8 caracteres, con mayuscula, minuscula, numero y caracter especial.';
    } else if (input.name === 'password' && mode === 'create-user' && value !== '' && !PASSWORD_RE.test(value)) {
      message = 'Minimo 8 caracteres, con mayuscula, minuscula, numero y caracter especial.';
    } else if (input.name === 'password' && mode === 'change' && value !== '' && !PASSWORD_RE.test(value)) {
      message = 'Minimo 8 caracteres, con mayuscula, minuscula, numero y caracter especial.';
    } else if (input.name === 'password_confirmation') {
      var pw = form ? form.elements.password.value : '';
      if (pw !== '' && value !== pw) {
        message = 'Las contrasenas no coinciden.';
      }
    }

    setError(input, message);
    return message === '';
  }

  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    form.querySelectorAll('input').forEach(function (input) {
      input.addEventListener('blur', function () {
        validateField(input);
      });
      input.addEventListener('input', function () {
        if (input.classList.contains('is-invalid')) {
          validateField(input);
        }
      });
    });

    form.addEventListener('submit', function (event) {
      var firstInvalid = null;

      var valid = Array.prototype.every.call(
        form.querySelectorAll('input'),
        function (input) {
          var ok = validateField(input);
          if (!ok && firstInvalid === null) {
            firstInvalid = input;
          }
          return ok;
        }
      );

      if (!valid) {
        event.preventDefault();
        if (firstInvalid) {
          firstInvalid.focus();
        }
      }
    });
  });
})();