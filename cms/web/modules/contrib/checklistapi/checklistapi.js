/**
 * @file
 * Defines JavaScript behaviors for the checklistapi module.
 */

(($, Drupal) => {
  /**
   * Updates the progress bar as checkboxes are changed.
   */
  Drupal.behaviors.checklistapiUpdateProgressBar = {
    attach(context) {
      const totalItems = $(":checkbox.checklistapi-item", context).length;
      const progressBar = $(
        "#checklistapi-checklist-form .progress__bar",
        context
      );
      const progressPercentage = $(
        "#checklistapi-checklist-form .progress__percentage",
        context
      );
      $(":checkbox.checklistapi-item", context).change(() => {
        const numItemsChecked = $(
          ":checkbox.checklistapi-item:checked",
          context
        ).length;
        const percentComplete = Math.round(
          (numItemsChecked / totalItems) * 100
        );
        const args = {};
        progressBar.css("width", `${percentComplete}%`);
        args["@complete"] = numItemsChecked;
        args["@total"] = totalItems;
        args["@percent"] = percentComplete;
        progressPercentage.html(
          Drupal.t("@complete of @total (@percent%)", args)
        );
      });
    },
  };

  /**
   * Provides the summary information for the checklist form vertical tabs.
   */
  Drupal.behaviors.checklistapiFieldsetSummaries = {
    attach(context) {
      $(
        "#checklistapi-checklist-form .vertical-tabs__panes > details",
        context
      ).drupalSetSummary((context) => {
        const total = $(":checkbox.checklistapi-item", context).length;
        const args = {};
        if (total) {
          args["@complete"] = $(
            ":checkbox.checklistapi-item:checked",
            context
          ).length;
          args["@total"] = total;
          args["@percent"] = Math.round(
            (args["@complete"] / args["@total"]) * 100
          );
          return Drupal.t("@complete of @total (@percent%)", args);
        }
      });
    },
  };

  /**
   * Adds dynamic item descriptions toggling.
   */
  Drupal.behaviors.checklistapiCompactModeLink = {
    attach(context) {
      const isCompactMode = $("#checklistapi-checklist-form", context).hasClass(
        "compact-mode"
      );
      const text = isCompactMode
        ? Drupal.t("Show item descriptions")
        : Drupal.t("Hide item descriptions");
      $("#checklistapi-checklist-form .compact-link", context).html(
        `<a href="#">${text}</a>`
      );
      $("#checklistapi-checklist-form .compact-link a", context).click(
        function () {
          $(this)
            .closest("#checklistapi-checklist-form")
            .toggleClass("compact-mode");
          $(this)
            .text(
              isCompactMode
                ? Drupal.t("Show item descriptions")
                : Drupal.t("Hide item descriptions")
            )
            .attr(
              "title",
              isCompactMode
                ? Drupal.t("Expand layout to include item descriptions.")
                : Drupal.t("Compress layout by hiding item descriptions.")
            );
          document.cookie = `Drupal.visitor.checklistapi_compact_mode=${
            isCompactMode ? 1 : 0
          }`;
          return false;
        }
      );
    },
  };

  /**
   * Prompts the user if they try to leave the page with unsaved changes.
   *
   * Note: Auto-checked items are not considered unsaved changes for the purpose
   * of this feature.
   */
  Drupal.behaviors.checklistapiPromptBeforeLeaving = {
    getFormState() {
      return $("#checklistapi-checklist-form :checkbox.checklistapi-item")
        .serializeArray()
        .toString();
    },
    attach() {
      const beginningState = this.getFormState();
      $(window).bind("beforeunload", () => {
        const endingState =
          Drupal.behaviors.checklistapiPromptBeforeLeaving.getFormState();
        if (beginningState !== endingState) {
          return Drupal.t(
            "Your changes will be lost if you leave the page without saving."
          );
        }
      });
      $("#checklistapi-checklist-form").submit(() => {
        $(window).unbind("beforeunload");
      });
      $("#checklistapi-checklist-form .clear-saved-progress").click(() => {
        $(window).unbind("beforeunload");
      });
    },
  };
})(jQuery, Drupal);
