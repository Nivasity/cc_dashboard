/**
 * Main
 */

'use strict';

let menu, animate;

(function () {
  const themeStorageKey = 'cc_theme_mode';

  const resolveThemeAssetsPath = function () {
    const html = document.documentElement;
    const assetsPath = html.getAttribute('data-assets-path') || 'assets/';
    return assetsPath.endsWith('/') ? assetsPath : assetsPath + '/';
  };

  const syncThemeImages = function (themeMode) {
    const isDark = themeMode === 'dark';
    const assetsPath = resolveThemeAssetsPath();
    const imageNodes = document.querySelectorAll('[data-app-dark-img][data-app-light-img]');

    imageNodes.forEach(function (imageNode) {
      const darkImage = imageNode.getAttribute('data-app-dark-img');
      const lightImage = imageNode.getAttribute('data-app-light-img');
      const targetImage = isDark ? darkImage : lightImage;
      if (!targetImage) {
        return;
      }

      const isAbsolute = /^(https?:)?\/\//i.test(targetImage) || targetImage.indexOf('assets/') === 0;
      imageNode.setAttribute('src', isAbsolute ? targetImage : assetsPath + 'img/' + targetImage);
    });
  };

  const syncThemeToggleUI = function (themeMode) {
    const isDark = themeMode === 'dark';
    const iconClassToAdd = isDark ? 'bx-sun' : 'bx-moon';
    const iconClassToRemove = isDark ? 'bx-moon' : 'bx-sun';
    const labelText = isDark ? 'Light' : 'Dark';

    document.querySelectorAll('[data-theme-toggle-icon]').forEach(function (iconNode) {
      iconNode.classList.remove(iconClassToRemove);
      iconNode.classList.add(iconClassToAdd);
    });

    document.querySelectorAll('[data-theme-toggle-label]').forEach(function (labelNode) {
      labelNode.textContent = labelText;
    });
  };

  const applyThemeMode = function (themeMode) {
    const html = document.documentElement;
    const finalTheme = themeMode === 'dark' ? 'dark' : 'light';
    const isDark = finalTheme === 'dark';

    html.classList.toggle('dark-style', isDark);
    html.classList.toggle('light-style', !isDark);
    html.setAttribute('data-theme-mode', finalTheme);

    try {
      window.localStorage.setItem(themeStorageKey, finalTheme);
    } catch (e) {
      // Ignore localStorage failures (private mode / browser restrictions).
    }

    syncThemeImages(finalTheme);
    syncThemeToggleUI(finalTheme);
  };

  const getCurrentThemeMode = function () {
    const html = document.documentElement;
    if (html.classList.contains('dark-style') || html.getAttribute('data-theme-mode') === 'dark') {
      return 'dark';
    }
    return 'light';
  };

  const bindThemeToggle = function () {
    const toggleNodes = document.querySelectorAll('[data-theme-toggle]');
    if (!toggleNodes.length) {
      return;
    }

    toggleNodes.forEach(function (toggleNode) {
      toggleNode.addEventListener('click', function () {
        const nextTheme = getCurrentThemeMode() === 'dark' ? 'light' : 'dark';
        applyThemeMode(nextTheme);
      });
    });
  };

  // Initialize menu
  //-----------------

  let layoutMenuEl = document.querySelectorAll('#layout-menu');
  layoutMenuEl.forEach(function (element) {
    menu = new Menu(element, {
      orientation: 'vertical',
      closeChildren: false
    });
    // Change parameter to true if you want scroll animation
    window.Helpers.scrollToActive((animate = false));
    window.Helpers.mainMenu = menu;
  });

  // Initialize menu togglers and bind click on each
  let menuToggler = document.querySelectorAll('.layout-menu-toggle');
  menuToggler.forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      window.Helpers.toggleCollapsed();
    });
  });

  // Display menu toggle (layout-menu-toggle) on hover with delay
  let delay = function (elem, callback) {
    let timeout = null;
    elem.onmouseenter = function () {
      // Set timeout to be a timer which will invoke callback after 300ms (not for small screen)
      if (!Helpers.isSmallScreen()) {
        timeout = setTimeout(callback, 300);
      } else {
        timeout = setTimeout(callback, 0);
      }
    };

    elem.onmouseleave = function () {
      // Clear any timers set to timeout
      document.querySelector('.layout-menu-toggle').classList.remove('d-block');
      clearTimeout(timeout);
    };
  };
  if (document.getElementById('layout-menu')) {
    delay(document.getElementById('layout-menu'), function () {
      // not for small screen
      if (!Helpers.isSmallScreen()) {
        document.querySelector('.layout-menu-toggle').classList.add('d-block');
      }
    });
  }

  // Display in main menu when menu scrolls
  let menuInnerContainer = document.getElementsByClassName('menu-inner'),
    menuInnerShadow = document.getElementsByClassName('menu-inner-shadow')[0];
  if (menuInnerContainer.length > 0 && menuInnerShadow) {
    menuInnerContainer[0].addEventListener('ps-scroll-y', function () {
      if (this.querySelector('.ps__thumb-y').offsetTop) {
        menuInnerShadow.style.display = 'block';
      } else {
        menuInnerShadow.style.display = 'none';
      }
    });
  }

  // Init helpers & misc
  // --------------------

  // Init BS Tooltip
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Accordion active class
  const accordionActiveFunction = function (e) {
    if (e.type == 'show.bs.collapse' || e.type == 'show.bs.collapse') {
      e.target.closest('.accordion-item').classList.add('active');
    } else {
      e.target.closest('.accordion-item').classList.remove('active');
    }
  };

  const accordionTriggerList = [].slice.call(document.querySelectorAll('.accordion'));
  const accordionList = accordionTriggerList.map(function (accordionTriggerEl) {
    accordionTriggerEl.addEventListener('show.bs.collapse', accordionActiveFunction);
    accordionTriggerEl.addEventListener('hide.bs.collapse', accordionActiveFunction);
  });

  // Auto update layout based on screen size
  window.Helpers.setAutoUpdate(true);

  // Toggle Password Visibility
  window.Helpers.initPasswordToggle();

  // Speech To Text
  window.Helpers.initSpeechToText();

  // Manage menu expanded/collapsed with templateCustomizer & local storage
  //------------------------------------------------------------------

  // If current layout is horizontal OR current window screen is small (overlay menu) than return from here
  if (window.Helpers.isSmallScreen()) {
    return;
  }

  // If current layout is vertical and current window screen is > small

  // Auto update menu collapsed/expanded based on the themeConfig
  window.Helpers.setCollapsed(true, false);

  applyThemeMode(getCurrentThemeMode());
  bindThemeToggle();
})();
