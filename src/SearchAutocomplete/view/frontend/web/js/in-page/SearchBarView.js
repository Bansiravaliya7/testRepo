/*eslint-disable */
define(["jquery"], function (_jquery) {
  var SearchBarView = function SearchBarView(props) {
    "use strict";

    this.props = props;
    props.visible.subscribe(function (visible) {
      visible && setTimeout(function () {
        var interval = setInterval(function () {
          var el = (0, _jquery)("[type=search]")[0];
          if (el) {
            el.focus();
            clearInterval(interval);
          }
        }, 10);
      }, 10);
    });
    (0, _jquery)(document).on("keyup", function (e) {
      if (e.key === "Escape") {
        if (props.query()) {
          props.query("");
        }
      }
    });
  };
  return {
    SearchBarView: SearchBarView
  };
});
//# sourceMappingURL=SearchBarView.js.map