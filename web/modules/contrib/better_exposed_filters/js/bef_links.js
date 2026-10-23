/**
 * @file
 * JavaScript code for bef_links.
 */
(function (once) {

  // Non-AJAX behavior: buttons don't navigate on their own, so intercept
  // clicks on non-ajax bef-links containers and follow the data-url.
  Drupal.behaviors.better_exposed_filters_links = {
    attach: function (context) {
      once('bef-links-links-non-ajax', '.bef-links:not(.bef-links-use-ajax)', context).forEach(function (element) {
        document.addEventListener('click', (e) => {
          const btn = e.target.closest('button[data-url]');
          if (!btn) return;
          e.stopImmediatePropagation();
          window.location.href = btn.dataset.url;
        });
      });
    }
  };

  // AJAX behavior: intercept clicks, update a hidden input, and submit.
  Drupal.behaviors.better_exposed_filters_select_as_links = {
    attach: function (context) {
      once('bef-links-use-ajax', '.bef-links.bef-links-use-ajax', context).forEach(function (element) {
        const links_name = element.getAttribute('data-name');
        const links_multiple = element.getAttribute('data-multiple');
        const form = element.closest('form');

        element.querySelectorAll('button').forEach((el) => el.addEventListener('click', (event) => {
          // Prevent the button from doing anything else (in particular the
          // non-ajax navigation handler above).
          event.preventDefault();

          const target = event.currentTarget;

          const link_value = target.getAttribute('data-bef-value');
          const base_name = links_name.replace(/\[]$/, '');
          const link_name = links_multiple ? base_name + '[' + link_value + ']' : links_name;
          const filter = form.querySelector('input[name="' + link_name + '"]');
          const filters = form.querySelectorAll('input[name^="' + base_name + '"]');

          if (target.classList.contains('bef-link--selected')) {
            // The previously selected option is selected again. Deselect it.
            target.classList.remove('bef-link--selected');
            target.setAttribute('aria-pressed', 'false');
            if (!links_multiple || link_value === 'All') {
              filters.forEach((el) => el.remove());
            }
            else {
              filter.remove();
            }
          }
          else {
            if (!links_multiple || link_value === 'All') {
              element.querySelectorAll('.bef-link--selected').forEach((el) => {
                el.classList.remove('bef-link--selected');
                el.setAttribute('aria-pressed', 'false');
              });
            }
            target.classList.add('bef-link--selected');
            target.setAttribute('aria-pressed', 'true');

            if (!filter) {
              const newFilter = document.createElement("input");
              newFilter.type = "hidden";
              newFilter.name = link_name;
              newFilter.value = link_value;
              element.appendChild(newFilter);
            }
            else {
              filter.value = link_value;
            }
          }

          // Submit the form.
          form.querySelector('.form-submit:not([data-drupal-selector*=edit-reset])').click();
        }));
      });
    }
  };
})(once);
