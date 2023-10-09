/*eslint-disable */
define([], function () {
  var IndexListView = function IndexListView(props) {
    "use strict";

    var _this = this;
    this.isViewAllVisible = function () {
      return _this.props.result().totalItems > 0;
    };
    this.viewAllUrl = function () {
      return _this.props.result().urlAll;
    };
    this.indexes = function () {
      return _this.props.result().indexes;
    };
    this.isActive = function (index) {
      return index.identifier == _this.props.activeIndex();
    };
    this.selectIndex = function (index) {
      _this.props.activeIndex(index.identifier);
    };
    this.props = props;
  };
  return {
    IndexListView: IndexListView
  };
});
//# sourceMappingURL=IndexListView.js.map