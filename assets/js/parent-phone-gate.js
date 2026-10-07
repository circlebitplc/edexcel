/**
 * Parent/Guardian WhatsApp Gate Client Engine
 * - Automatic IP-based country detection with fallback
 * - Interactive searchable country selector
 * - Local-first phone entry with intelligent dial-code auto-switching
 * - Real-time formatting & E.164 validation per country
 * - Self-phone collision guard & AJAX submission
 */
(function () {
  'use strict';

  // ── Duplicate-gate guard ──────────────────────────────────────────────────
  // If the PHP double-include bug caused more than one #parentPhoneGate to be
  // rendered, the extras sit invisible on top and swallow all pointer/keyboard
  // events.  Destroy every duplicate, keeping only the first one.
  (function removeDuplicateGates() {
    var all = document.querySelectorAll('[id="parentPhoneGate"]');
    for (var i = 1; i < all.length; i++) {
      if (all[i].parentNode) {
        all[i].parentNode.removeChild(all[i]);
      }
    }
  })();
  // ─────────────────────────────────────────────────────────────────────────

  var gate = document.getElementById('parentPhoneGate');
  if (!gate) return;

  // Stop any external focus trap (e.g. Bootstrap FocusTrap) from stealing focus from gate inputs
  gate.addEventListener('focusin', function (e) {
    e.stopImmediatePropagation();
  }, true);

  // Clean up any background Bootstrap modals and backdrops that could block input
  if (window.bootstrap) {
    try {
      var openModals = document.querySelectorAll('.modal.show, .modal');
      for (var k = 0; k < openModals.length; k++) {
        var inst = bootstrap.Modal.getInstance(openModals[k]);
        if (inst) { inst.hide(); inst.dispose(); }
      }
    } catch (e) {}
  }
  document.querySelectorAll('.modal-backdrop').forEach(function (b) {
    b.parentNode && b.parentNode.removeChild(b);
  });
  document.body.classList.remove('modal-open');

  var missingModal = document.getElementById('missingMobileModal');
  if (missingModal) {
    missingModal.style.display = 'none';
  }

  var form = document.getElementById('parentPhoneGateForm');
  var phoneInput = document.getElementById('parentPhoneGateWhatsapp');
  var nameInput = document.getElementById('parentPhoneGateName');
  var statusIcon = document.getElementById('parentPhoneGateStatus');
  var errorBox = document.getElementById('parentPhoneGateError');
  var hintBox = document.getElementById('parentPhoneGateHint');
  var submitBtn = document.getElementById('parentPhoneGateSubmit');
  var ajaxFlag = document.getElementById('parentPhoneGateAjax');
  var wrap = document.getElementById('parentPhoneGateWrap');
  var successOverlay = gate.querySelector('.parent-phone-gate-success');

  // Country selector elements
  var countryBtn = document.getElementById('parentPhoneGateCountryBtn');
  var countryFlagEl = document.getElementById('parentPhoneGateFlag');
  var countryDialEl = document.getElementById('parentPhoneGateDialCode');
  var countryIsoInput = document.getElementById('parentPhoneGateCountryIso');
  var dialCodeInput = document.getElementById('parentPhoneGateDialCodeInput');
  var dropdown = document.getElementById('parentPhoneGateCountryDropdown');
  var searchInput = document.getElementById('parentPhoneGateCountrySearch');
  var clearSearchBtn = document.getElementById('parentPhoneGateCountryClear');
  var countryList = document.getElementById('parentPhoneGateCountryList');

  var ownPhone = (gate.getAttribute('data-own-phone') || '').trim();
  var userManuallySelected = false;

  // Lock body scroll while modal is active
  document.body.classList.add('parent-phone-gate-open');

  // Country Master Directory
  var COUNTRIES = [
    { iso: 'LK', name: 'Sri Lanka', dial: '+94', flag: '🇱🇰', sample: '077 123 4567', min: 9, max: 10 },
    { iso: 'GB', name: 'United Kingdom', dial: '+44', flag: '🇬🇧', sample: '07911 123456', min: 10, max: 11 },
    { iso: 'AE', name: 'United Arab Emirates', dial: '+971', flag: '🇦🇪', sample: '050 123 4567', min: 9, max: 10 },
    { iso: 'QA', name: 'Qatar', dial: '+974', flag: '🇶🇦', sample: '3312 3456', min: 8, max: 8 },
    { iso: 'OM', name: 'Oman', dial: '+968', flag: '🇴🇲', sample: '9123 4567', min: 8, max: 8 },
    { iso: 'SA', name: 'Saudi Arabia', dial: '+966', flag: '🇸🇦', sample: '050 123 4567', min: 9, max: 10 },
    { iso: 'AU', name: 'Australia', dial: '+61', flag: '🇦🇺', sample: '0412 345 678', min: 9, max: 10 },
    { iso: 'IN', name: 'India', dial: '+91', flag: '🇮🇳', sample: '98765 43210', min: 10, max: 10 },
    { iso: 'US', name: 'United States', dial: '+1', flag: '🇺🇸', sample: '(555) 123-4567', min: 10, max: 10 },
    { iso: 'CA', name: 'Canada', dial: '+1', flag: '🇨🇦', sample: '(555) 123-4567', min: 10, max: 10 },
    { iso: 'SG', name: 'Singapore', dial: '+65', flag: '🇸🇬', sample: '8123 4567', min: 8, max: 8 },
    { iso: 'MY', name: 'Malaysia', dial: '+60', flag: '🇲🇾', sample: '012-345 6789', min: 9, max: 11 },
    { iso: 'MV', name: 'Maldives', dial: '+960', flag: '🇲🇻', sample: '712 3456', min: 7, max: 7 },
    { iso: 'KW', name: 'Kuwait', dial: '+965', flag: '🇰🇼', sample: '5123 4567', min: 8, max: 8 },
    { iso: 'BH', name: 'Bahrain', dial: '+973', flag: '🇧🇭', sample: '3612 3456', min: 8, max: 8 },
    { iso: 'NZ', name: 'New Zealand', dial: '+64', flag: '🇳🇿', sample: '021 123 4567', min: 8, max: 10 },
    { iso: 'IT', name: 'Italy', dial: '+39', flag: '🇮🇹', sample: '312 345 6789', min: 9, max: 11 },
    { iso: 'DE', name: 'Germany', dial: '+49', flag: '🇩🇪', sample: '0151 1234567', min: 10, max: 12 },
    { iso: 'FR', name: 'France', dial: '+33', flag: '🇫🇷', sample: '06 12 34 56 78', min: 9, max: 10 },
    { iso: 'IE', name: 'Ireland', dial: '+353', flag: '🇮🇪', sample: '085 123 4567', min: 9, max: 10 },
    { iso: 'PK', name: 'Pakistan', dial: '+92', flag: '🇵🇰', sample: '0300 1234567', min: 10, max: 11 },
    { iso: 'BD', name: 'Bangladesh', dial: '+880', flag: '🇧🇩', sample: '01712 345678', min: 10, max: 11 },
    { iso: 'NP', name: 'Nepal', dial: '+977', flag: '🇳🇵', sample: '984 1234567', min: 10, max: 10 },
    { iso: 'HK', name: 'Hong Kong', dial: '+852', flag: '🇭🇰', sample: '9123 4567', min: 8, max: 8 },
    { iso: 'JP', name: 'Japan', dial: '+81', flag: '🇯🇵', sample: '090 1234 5678', min: 10, max: 11 },
    { iso: 'KR', name: 'South Korea', dial: '+82', flag: '🇰🇷', sample: '010 1234 5678', min: 10, max: 11 },
    { iso: 'CN', name: 'China', dial: '+86', flag: '🇨🇳', sample: '138 1234 5678', min: 11, max: 11 },
    { iso: 'ID', name: 'Indonesia', dial: '+62', flag: '🇮🇩', sample: '0812 3456 789', min: 9, max: 13 },
    { iso: 'TH', name: 'Thailand', dial: '+66', flag: '🇹🇭', sample: '081 234 5678', min: 9, max: 10 },
    { iso: 'PH', name: 'Philippines', dial: '+63', flag: '🇵🇭', sample: '0917 123 4567', min: 10, max: 10 },
    { iso: 'VN', name: 'Vietnam', dial: '+84', flag: '🇻🇳', sample: '091 234 5678', min: 9, max: 10 },
    { iso: 'ZA', name: 'South Africa', dial: '+27', flag: '🇿🇦', sample: '071 123 4567', min: 9, max: 10 },
    { iso: 'EG', name: 'Egypt', dial: '+20', flag: '🇪🇬', sample: '010 1234 5678', min: 10, max: 11 },
    { iso: 'NG', name: 'Nigeria', dial: '+234', flag: '🇳🇬', sample: '0802 123 4567', min: 10, max: 11 },
    { iso: 'KE', name: 'Kenya', dial: '+254', flag: '🇰🇪', sample: '0712 345 678', min: 9, max: 10 },
    { iso: 'TR', name: 'Turkey', dial: '+90', flag: '🇹🇷', sample: '0532 123 4567', min: 10, max: 11 },
    { iso: 'NL', name: 'Netherlands', dial: '+31', flag: '🇳🇱', sample: '06 12345678', min: 9, max: 10 },
    { iso: 'CH', name: 'Switzerland', dial: '+41', flag: '🇨🇭', sample: '078 123 45 67', min: 9, max: 10 },
    { iso: 'SE', name: 'Sweden', dial: '+46', flag: '🇸🇪', sample: '070 123 45 67', min: 9, max: 10 },
    { iso: 'NO', name: 'Norway', dial: '+47', flag: '🇳🇴', sample: '412 34 567', min: 8, max: 8 },
    { iso: 'DK', name: 'Denmark', dial: '+45', flag: '🇩🇰', sample: '20 12 34 56', min: 8, max: 8 },
    { iso: 'FI', name: 'Finland', dial: '+358', flag: '🇫🇮', sample: '040 1234567', min: 9, max: 10 },
    { iso: 'ES', name: 'Spain', dial: '+34', flag: '🇪🇸', sample: '612 34 56 78', min: 9, max: 9 },
    { iso: 'PT', name: 'Portugal', dial: '+351', flag: '🇵🇹', sample: '912 345 678', min: 9, max: 9 },
    { iso: 'GR', name: 'Greece', dial: '+30', flag: '🇬🇷', sample: '691 234 5678', min: 10, max: 10 },
    { iso: 'CY', name: 'Cyprus', dial: '+357', flag: '🇨🇾', sample: '99 123456', min: 8, max: 8 },
    { iso: 'JO', name: 'Jordan', dial: '+962', flag: '🇯🇴', sample: '07 9123 4567', min: 9, max: 10 },
    { iso: 'LB', name: 'Lebanon', dial: '+961', flag: '🇱🇧', sample: '03 123 456', min: 7, max: 8 },
    { iso: 'MU', name: 'Mauritius', dial: '+230', flag: '🇲🇺', sample: '5123 4567', min: 7, max: 8 },
    { iso: 'SC', name: 'Seychelles', dial: '+248', flag: '🇸🇨', sample: '251 2345', min: 7, max: 7 },
    { iso: 'BR', name: 'Brazil', dial: '+55', flag: '🇧🇷', sample: '(11) 91234-5678', min: 10, max: 11 },
    { iso: 'MX', name: 'Mexico', dial: '+52', flag: '🇲🇽', sample: '55 1234 5678', min: 10, max: 10 },
    { iso: 'RU', name: 'Russia', dial: '+7', flag: '🇷🇺', sample: '912 345-67-89', min: 10, max: 10 },
    { iso: 'KZ', name: 'Kazakhstan', dial: '+7', flag: '🇰🇿', sample: '701 123 4567', min: 10, max: 10 },
    { iso: 'BE', name: 'Belgium', dial: '+32', flag: '🇧🇪', sample: '0470 12 34 56', min: 9, max: 10 },
    { iso: 'AT', name: 'Austria', dial: '+43', flag: '🇦🇹', sample: '0664 1234567', min: 9, max: 11 },
    { iso: 'PL', name: 'Poland', dial: '+48', flag: '🇵🇱', sample: '512 345 678', min: 9, max: 9 },
    { iso: 'CZ', name: 'Czech Republic', dial: '+420', flag: '🇨🇿', sample: '601 123 456', min: 9, max: 9 }
  ];

  var countryMap = {};
  var sortedDials = [];
  COUNTRIES.forEach(function (c) {
    countryMap[c.iso] = c;
    var rawDial = c.dial.replace(/\D+/g, '');
    sortedDials.push({ code: rawDial, iso: c.iso });
  });
  // Sort dial codes by length descending so multi-digit codes match first
  sortedDials.sort(function (a, b) {
    return b.code.length - a.code.length;
  });

  // Current active country
  var currentIso = (countryIsoInput && countryIsoInput.value) || gate.getAttribute('data-detected-country') || 'LK';
  var currentCountry = countryMap[currentIso] || countryMap['LK'];

  function updateCountry(iso, preserveInput) {
    var c = countryMap[iso];
    if (!c) return;
    currentIso = c.iso;
    currentCountry = c;

    if (countryFlagEl) countryFlagEl.textContent = c.flag;
    if (countryDialEl) countryDialEl.textContent = c.dial;
    if (countryIsoInput) countryIsoInput.value = c.iso;
    if (dialCodeInput) dialCodeInput.value = c.dial;

    if (phoneInput) {
      phoneInput.placeholder = c.sample;
      if (!preserveInput && !phoneInput.value.trim()) {
        phoneInput.value = '';
      }
    }

    if (hintBox) {
      if (c.iso === 'LK') {
        hintBox.textContent = 'Use 077 123 4567 — not your own mobile number.';
      } else {
        hintBox.textContent = 'Enter parent ' + c.name + ' WhatsApp number (e.g. ' + c.sample + ').';
      }
    }

    // Update selected class in dropdown
    if (countryList) {
      var items = countryList.querySelectorAll('.parent-phone-gate-country-item');
      items.forEach(function (it) {
        if (it.getAttribute('data-iso') === c.iso) {
          it.classList.add('is-selected');
          it.setAttribute('aria-selected', 'true');
        } else {
          it.classList.remove('is-selected');
          it.setAttribute('aria-selected', 'false');
        }
      });
    }

    if (phoneInput && phoneInput.value.trim()) {
      var formatted = formatPhone(phoneInput.value, c);
      phoneInput.value = formatted;
      validate();
    }
  }

  // Formatting per country
  function formatPhone(val, c) {
    c = c || currentCountry;
    var digits = String(val || '').replace(/\D+/g, '');

    // Sri Lanka: 07X XXX XXXX
    if (c.iso === 'LK') {
      if (digits.length <= 3) return digits;
      if (digits.length <= 6) return digits.substring(0, 3) + ' ' + digits.substring(3);
      return digits.substring(0, 3) + ' ' + digits.substring(3, 6) + ' ' + digits.substring(6, 10);
    }

    // UK: 07XXX XXXXXX (or 5-6)
    if (c.iso === 'GB') {
      if (digits.length <= 5) return digits;
      return digits.substring(0, 5) + ' ' + digits.substring(5, 11);
    }

    // UAE: 05X XXX XXXX
    if (c.iso === 'AE') {
      if (digits.length <= 3) return digits;
      if (digits.length <= 6) return digits.substring(0, 3) + ' ' + digits.substring(3);
      return digits.substring(0, 3) + ' ' + digits.substring(3, 6) + ' ' + digits.substring(6, 10);
    }

    // US / Canada: (XXX) XXX-XXXX
    if (c.iso === 'US' || c.iso === 'CA') {
      if (digits.length <= 3) return digits;
      if (digits.length <= 6) return '(' + digits.substring(0, 3) + ') ' + digits.substring(3);
      return '(' + digits.substring(0, 3) + ') ' + digits.substring(3, 6) + '-' + digits.substring(6, 10);
    }

    // India: XXXXX XXXXX
    if (c.iso === 'IN') {
      if (digits.length <= 5) return digits;
      return digits.substring(0, 5) + ' ' + digits.substring(5, 10);
    }

    // General: blocks of 3-4
    if (digits.length <= 4) return digits;
    if (digits.length <= 7) return digits.substring(0, 3) + ' ' + digits.substring(3);
    if (digits.length <= 11) return digits.substring(0, 3) + ' ' + digits.substring(3, 7) + ' ' + digits.substring(7);
    return digits.substring(0, 4) + ' ' + digits.substring(4, 8) + ' ' + digits.substring(8, 14);
  }

  // Full E.164 normalization for submission & comparison
  function toFullE164Digits(val, c) {
    c = c || currentCountry;
    var raw = String(val || '').trim();
    if (!raw) return '';

    var digits = raw.replace(/\D+/g, '');
    var dialDigits = c.dial.replace(/\D+/g, '');

    // User explicitly typed leading '+'
    if (raw.indexOf('+') === 0 || raw.indexOf('00') === 0) {
      if (raw.indexOf('00') === 0) digits = digits.substring(2);
      return digits;
    }

    // If starts with leading 0 (e.g. 077... or 07911...), strip 0 and prepend dial code
    if (digits.charAt(0) === '0' && digits.length > 1) {
      return dialDigits + digits.substring(1);
    }

    // If already starts with dial code digits
    if (digits.indexOf(dialDigits) === 0) {
      return digits;
    }

    return dialDigits + digits;
  }

  function showError(msg) {
    if (window.showToast) {
      window.showToast(msg || 'Enter a valid number', 'error');
    }
    if (!errorBox) return;
    errorBox.textContent = msg || 'Enter a valid number';
    errorBox.classList.remove('is-empty');
    if (wrap) wrap.classList.add('is-invalid');
    if (statusIcon) {
      statusIcon.classList.remove('is-valid');
      statusIcon.classList.add('is-invalid');
      statusIcon.innerHTML = '<i class="bi bi-exclamation-circle"></i>';
    }
  }

  function clearError() {
    if (!errorBox) return;
    errorBox.textContent = '';
    errorBox.classList.add('is-empty');
    if (wrap) wrap.classList.remove('is-invalid');
    if (statusIcon) {
      statusIcon.classList.remove('is-invalid');
      statusIcon.classList.add('is-valid');
      statusIcon.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
    }
  }

  function validate(checkName) {
    if (checkName === true) {
      var nameVal = (nameInput ? nameInput.value : '').trim();
      if (!nameVal) {
        showError('Please enter your parent or guardian’s name.');
        return false;
      }
    }

    var raw = (phoneInput.value || '').trim();
    if (!raw) {
      if (statusIcon) {
        statusIcon.classList.remove('is-valid', 'is-invalid');
        statusIcon.innerHTML = '<i class="bi bi-phone"></i>';
      }
      if (wrap) wrap.classList.remove('is-invalid');
      if (checkName === true) {
        showError('Please enter a parent WhatsApp number.');
      } else if (errorBox && errorBox.textContent.indexOf('name') === -1) {
        errorBox.classList.add('is-empty');
      }
      return false;
    }

    var digits = raw.replace(/\D+/g, '');
    var fullE164 = toFullE164Digits(raw, currentCountry);

    // Sri Lanka validation: 07X XXX XXXX (or 947XXXXXXXX)
    if (currentCountry.iso === 'LK') {
      var isLocalOk = (digits.length === 10 && digits.charAt(0) === '0' && digits.charAt(1) === '7');
      var is9Ok = (digits.length === 9 && digits.charAt(0) === '7');
      var isE164Ok = /^947\d{8}$/.test(fullE164);
      if (!isLocalOk && !is9Ok && !isE164Ok) {
        showError('Please enter a valid Sri Lankan mobile (e.g. 077 123 4567).');
        return false;
      }
    } else {
      // International validation
      if (digits.length < currentCountry.min || digits.length > currentCountry.max + 2) {
        showError('Please check phone number length for ' + currentCountry.name + '.');
        return false;
      }
      if (!/^[1-9]\d{7,14}$/.test(fullE164)) {
        showError('Enter a valid phone number.');
        return false;
      }
    }

    // Own login phone collision check
    var ownDigits = (ownPhone || '').replace(/\D+/g, '');
    if (ownDigits) {
      if (ownDigits.charAt(0) === '0' && ownDigits.length === 10) ownDigits = '94' + ownDigits.substring(1);
      if (/^7\d{8}$/.test(ownDigits)) ownDigits = '94' + ownDigits;
      if (fullE164 === ownDigits) {
        showError('Use your parent or guardian’s number, not your own mobile number.');
        return false;
      }
    }

    clearError();
    return true;
  }

  // Handle user typing: auto-switch country if user types '+' or international prefix
  function handlePhoneInput() {
    var val = phoneInput.value;
    var trimmed = val.trim();

    // Check if user entered leading '+' with country code
    if (trimmed.indexOf('+') === 0) {
      var numeric = trimmed.substring(1).replace(/\D+/g, '');
      if (numeric.length === 0) {
        phoneInput.value = '+';
        return;
      }
      for (var i = 0; i < sortedDials.length; i++) {
        var d = sortedDials[i];
        if (numeric.indexOf(d.code) === 0) {
          userManuallySelected = true;
          updateCountry(d.iso, true);
          var remaining = numeric.substring(d.code.length);
          if (d.iso === 'LK' && remaining.charAt(0) !== '0' && remaining.length > 0) {
            remaining = '0' + remaining;
          }
          phoneInput.value = formatPhone(remaining, currentCountry);
          validate();
          return;
        }
      }
      // If still typing the dial code (e.g. "+9", "+97"), preserve '+'
      if (numeric.length <= 4) {
        phoneInput.value = '+' + numeric;
        return;
      }
    }

    // Check if user entered leading '00' international prefix
    if (trimmed.indexOf('00') === 0) {
      var numeric00 = trimmed.substring(2).replace(/\D+/g, '');
      for (var j = 0; j < sortedDials.length; j++) {
        var d00 = sortedDials[j];
        if (numeric00.indexOf(d00.code) === 0) {
          userManuallySelected = true;
          updateCountry(d00.iso, true);
          var rem00 = numeric00.substring(d00.code.length);
          if (d00.iso === 'LK' && rem00.charAt(0) !== '0' && rem00.length > 0) {
            rem00 = '0' + rem00;
          }
          phoneInput.value = formatPhone(rem00, currentCountry);
          validate();
          return;
        }
      }
    }

    // If LK is active and user typed 947... without '+'
    if (currentCountry.iso === 'LK') {
      var rawDigits = trimmed.replace(/\D+/g, '');
      if (rawDigits.indexOf('947') === 0 && rawDigits.length >= 10) {
        phoneInput.value = formatPhone('0' + rawDigits.substring(2), currentCountry);
        validate();
        return;
      }
    }

    var formatted = formatPhone(val, currentCountry);
    if (formatted !== val) {
      phoneInput.value = formatted;
    }
    validate();
  }

  if (nameInput) {
    nameInput.addEventListener('input', function () {
      if (this.value.trim() && errorBox && errorBox.textContent.indexOf('name') !== -1) {
        clearError();
      }
    });
    nameInput.addEventListener('blur', function () {
      if (!this.value.trim() && phoneInput && phoneInput.value.trim()) {
        showError('Please enter your parent or guardian’s name.');
      }
    });
  }

  if (phoneInput) {
    phoneInput.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace') {
        var start = this.selectionStart;
        var end = this.selectionEnd;
        if (start === end && start > 0 && this.value.charAt(start - 1) === ' ') {
          e.preventDefault();
          this.value = this.value.substring(0, start - 2) + this.value.substring(start);
          this.setSelectionRange(start - 2, start - 2);
          handlePhoneInput();
        }
      }
    });
    phoneInput.addEventListener('input', handlePhoneInput);
    phoneInput.addEventListener('blur', function () {
      if (this.value.trim()) validate(false);
    });
  }

  // ------------------------------------------------------------
  // Country Dropdown Toggle & Search
  // ------------------------------------------------------------
  function openDropdown() {
    if (!dropdown) return;
    dropdown.removeAttribute('hidden');
    if (countryBtn) countryBtn.setAttribute('aria-expanded', 'true');
    if (searchInput) {
      searchInput.value = '';
      filterCountries('');
      setTimeout(function () {
        searchInput.focus();
      }, 50);
    }
  }

  function closeDropdown() {
    if (!dropdown) return;
    dropdown.setAttribute('hidden', '');
    if (countryBtn) countryBtn.setAttribute('aria-expanded', 'false');
  }

  function toggleDropdown() {
    if (dropdown && dropdown.hasAttribute('hidden')) {
      openDropdown();
    } else {
      closeDropdown();
    }
  }

  if (countryBtn) {
    countryBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      toggleDropdown();
    });
  }

  // Filter country list
  function filterCountries(q) {
    if (!countryList) return;
    var query = (q || '').trim().toLowerCase().replace(/^\+/, '');
    var items = countryList.querySelectorAll('.parent-phone-gate-country-item');
    var visibleCount = 0;

    items.forEach(function (it) {
      var name = (it.getAttribute('data-name') || '').toLowerCase();
      var iso = (it.getAttribute('data-iso') || '').toLowerCase();
      var dial = (it.getAttribute('data-dial') || '').replace(/\D+/g, '');

      var matches = !query || name.indexOf(query) !== -1 || iso.indexOf(query) !== -1 || dial.indexOf(query) !== -1;
      if (matches) {
        it.style.display = '';
        visibleCount++;
      } else {
        it.style.display = 'none';
      }
    });

    var emptyEl = countryList.querySelector('.parent-phone-gate-country-empty');
    if (visibleCount === 0) {
      if (!emptyEl) {
        emptyEl = document.createElement('div');
        emptyEl.className = 'parent-phone-gate-country-empty';
        emptyEl.textContent = 'No matching country found';
        countryList.appendChild(emptyEl);
      }
      emptyEl.style.display = 'block';
    } else if (emptyEl) {
      emptyEl.style.display = 'none';
    }

    if (clearSearchBtn) {
      clearSearchBtn.hidden = !query;
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      filterCountries(this.value);
    });

    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeDropdown();
        if (phoneInput) phoneInput.focus();
      } else if (e.key === 'Enter') {
        e.preventDefault();
        var firstVisible = countryList.querySelector('.parent-phone-gate-country-item:not([style*="display: none"])');
        if (firstVisible) {
          firstVisible.click();
        }
      }
    });
  }

  if (clearSearchBtn) {
    clearSearchBtn.addEventListener('click', function () {
      if (searchInput) {
        searchInput.value = '';
        filterCountries('');
        searchInput.focus();
      }
    });
  }

  // Select country from list item
  if (countryList) {
    countryList.addEventListener('click', function (e) {
      var item = e.target.closest('.parent-phone-gate-country-item');
      if (!item) return;
      var iso = item.getAttribute('data-iso');
      if (iso) {
        userManuallySelected = true;
        updateCountry(iso, false);
        closeDropdown();
        if (phoneInput) phoneInput.focus();
      }
    });
  }

  // Close dropdown on outside click
  document.addEventListener('click', function (e) {
    if (!dropdown || dropdown.hasAttribute('hidden')) return;
    if (!dropdown.contains(e.target) && !countryBtn.contains(e.target)) {
      closeDropdown();
    }
  });

  // ------------------------------------------------------------
  // Asynchronous IP Detection Fallback
  // ------------------------------------------------------------
  function asyncDetectCountry() {
    // If user already typed or manually picked, don't overwrite
    if (userManuallySelected || (phoneInput && phoneInput.value.trim())) return;

    var detected = gate.getAttribute('data-detected-country') || '';
    if (detected && detected !== 'LK') {
      // Server already detected non-LK country from Cloudflare or GeoIP
      updateCountry(detected, true);
      return;
    }

    // Query our fast internal geo endpoint or external fallback
    var geoEndpoint = '/ajax/geo_detect.php';
    fetch(geoEndpoint, { signal: (AbortSignal && AbortSignal.timeout ? AbortSignal.timeout(1800) : undefined) })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.country && countryMap[data.country]) {
          if (!userManuallySelected && (!phoneInput || !phoneInput.value.trim())) {
            updateCountry(data.country, true);
          }
        }
      })
      .catch(function () {
        // Silent fallback: remains at server initial country (LK)
      });
  }

  // Initial load
  updateCountry(currentIso, true);
  asyncDetectCountry();

  // If phoneInput is already filled on render, format & validate
  if (phoneInput && phoneInput.value.trim()) {
    phoneInput.value = formatPhone(phoneInput.value, currentCountry);
    validate();
  }

  // ------------------------------------------------------------
  // Form Submission
  // ------------------------------------------------------------
  if (form) {
    form.setAttribute('data-ui-skip', '1');
    form.addEventListener('submit', function (e) {
      var nameVal = (nameInput ? nameInput.value : '').trim();
      if (!nameVal) {
        e.preventDefault();
        showError('Please enter your parent or guardian’s name.');
        if (nameInput) nameInput.focus();
        return;
      }

      if (!validate(true)) {
        e.preventDefault();
        if (phoneInput) phoneInput.focus();
        return;
      }

      // Synchronize hidden values
      if (countryIsoInput) countryIsoInput.value = currentCountry.iso;
      if (dialCodeInput) dialCodeInput.value = currentCountry.dial;

      if (!window.fetch) {
        // Native fallback
        return;
      }

      e.preventDefault();

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.setAttribute('data-orig-text', submitBtn.textContent);
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving…';
      }

      if (ajaxFlag) ajaxFlag.value = '1';
      var formData = new FormData(form);

      fetch(form.action || window.location.href, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
      .then(function (res) {
        return res.json().catch(function () {
          return { ok: false, message: 'Server response error — please try again.' };
        });
      })
      .then(function (data) {
        if (data && data.ok) {
          if (successOverlay) {
            successOverlay.classList.add('is-visible');
          }
          setTimeout(function () {
            window.location.reload();
          }, 850);
        } else {
          var msg = (data && data.message) ? data.message : 'Could not save parent number. Please try again.';
          // If CSRF expired, fall back to a native form submit which will get a fresh token
          if (msg.toLowerCase().indexOf('token') !== -1 || msg.toLowerCase().indexOf('security') !== -1) {
            if (ajaxFlag) ajaxFlag.value = '0';
            form.submit();
            return;
          }
          showError(msg);
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = submitBtn.getAttribute('data-orig-text') || 'Continue to portal';
          }
        }
      })
      .catch(function () {
        // Network fallback
        if (ajaxFlag) ajaxFlag.value = '0';
        form.submit();
      });
    });
  }
})();
