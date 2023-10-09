/*eslint-disable */
function _extends() { _extends = Object.assign ? Object.assign.bind() : function (target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i]; for (var key in source) { if (Object.prototype.hasOwnProperty.call(source, key)) { target[key] = source[key]; } } } return target; }; return _extends.apply(this, arguments); }
define(["underscore", "knockout", "jquery", "Mirasvit_SearchAutocomplete/js/lib/jquery.ui.slider"], function (_underscore, _knockout, _jquery, _jqueryUi) {
  var SliderSelector = "[data-slider='price']";
  var SidebarView = function SidebarView(props) {
    "use strict";

    var _this = this;
    this.setBuckets = function (indexes, indexIdentifier) {
      var buckets = [];
      var activeBuckets = [];
      _underscore.each(indexes, function (idx) {
        if (idx.identifier != indexIdentifier) {
          return;
        }
        _underscore.each(idx.buckets, function (bucket) {
          var bucketItems = [];
          var activeBucketItems = [];
          if (bucket.code == "price") {
            var min = bucket.min;
            var max = bucket.max;
            if (_this.priceMin() == -1 || _this.priceMin() > min) {
              _this.priceMin(min);
            }
            if (_this.priceMax() == -1 || _this.priceMax() < max) {
              _this.priceMax(max);
            }

            // if (this.priceMin() == -1 || this.priceMin() == this.priceFrom()) {
            //     this.priceMin(bucket.min as number)
            //     this.priceFrom(bucket.min as number)
            // }
            // if (this.priceMax() == -1 || this.priceMax() == this.priceTo()) {
            //     this.priceMax(bucket.max as number)
            //     this.priceTo(bucket.max as number)
            // }
          }

          _underscore.each(bucket.items, function (item) {
            var filter = _this.props.filterList().get(bucket.code);
            var state = filter && filter.indexOf(item.key) >= 0 ? true : false;
            if (state) {
              activeBucketItems.push(_extends({}, item, {
                isActive: state,
                select: function select() {
                  return _this.selectItem(bucket.code, item.key);
                }
              }));
            }
            bucketItems.push(_extends({}, item, {
              isActive: state,
              select: function select() {
                return _this.selectItem(bucket.code, item.key);
              }
            }));
          });
          if (bucketItems.length > 0 || bucket.code == "price") {
            buckets.push(_extends({}, bucket, {
              items: bucketItems,
              isExpanded: !_underscore.include(_this.collapses(), bucket.code),
              expand: function expand() {
                var collapses = _underscore.clone(_this.collapses());
                if (_underscore.include(collapses, bucket.code)) {
                  collapses = _underscore.without(collapses, bucket.code);
                } else {
                  collapses.push(bucket.code);
                }
                _this.collapses(collapses);
                _this.setBuckets(_this.props.result().indexes, _this.props.activeIndex());
              }
            }));
          }
          if (activeBucketItems.length > 0) {
            activeBuckets.push(_extends({}, bucket, {
              items: activeBucketItems,
              isExpanded: true,
              expand: _underscore.noop
            }));
          }
        });
      });
      _this.buckets(buckets);
      _this.activeBuckets(activeBuckets);
      _underscore.each(indexes, function (idx) {
        if (idx.identifier != indexIdentifier) {
          return;
        }
        _underscore.each(idx.buckets, function (bucket) {
          if (bucket.code == "price") {
            waitForElementToDisplay(SliderSelector, function () {
              (0, _jquery)(SliderSelector).slider({
                range: true,
                min: _this.priceMin(),
                max: _this.priceMax(),
                values: [_this.priceFrom() != -1 ? _this.priceFrom() : _this.priceMin(), _this.priceTo() != -1 ? _this.priceTo() : _this.priceMax()],
                slide: function slide(e, ui) {
                  _this.priceFrom(ui.values[0] !== null && ui.values[0] >= 0 ? ui.values[0] : 0);
                  _this.priceTo(ui.values[1] !== null && ui.values[1] >= 0 ? ui.values[1] : 0);
                },
                change: function change(e, ui) {
                  _this.priceFrom(ui.values[0] !== null && ui.values[0] >= 0 ? ui.values[0] : 0);
                  _this.priceTo(ui.values[1] !== null && ui.values[1] >= 0 ? ui.values[1] : 0);
                  _this.selectItem("price", _this.priceFrom() + "_" + _this.priceTo());
                },
                step: 1
              });
            }, 10, 10000);
          }
        });
      });
    };
    this.selectItem = function (bucketCode, key) {
      var map = _this.props.filterList();
      if (bucketCode === "price") {
        var mapItem = map.get("price");
        if (mapItem) {
          var indexOf = mapItem.indexOf(key);
          if (indexOf >= 0) {
            if (mapItem[indexOf] == key) {
              return;
            }
          }
          map.set(bucketCode, [key]);
          _this.props.filterList(map);
        } else {
          map.set(bucketCode, [key]);
          _this.props.filterList(map);
        }
      } else {
        var _mapItem = map.get(bucketCode);
        if (_mapItem) {
          var _indexOf = _mapItem.indexOf(key);
          if (_indexOf >= 0) {
            _mapItem.splice(_indexOf, 1);
            if (_mapItem.length > 0) {
              map.set(bucketCode, _mapItem);
            } else {
              map.delete(bucketCode);
            }
          } else {
            _mapItem.push(key);
            map.set(bucketCode, _mapItem);
          }
        } else {
          map.set(bucketCode, [key]);
        }
        _this.props.filterList(map);
      }
    };
    this.props = props;
    this.buckets = _knockout.observableArray([]);
    this.activeBuckets = _knockout.observableArray([]);
    this.collapses = _knockout.observableArray([]);
    this.priceFrom = _knockout.observable(-1);
    this.priceTo = _knockout.observable(-1);
    this.priceMin = _knockout.observable(-1);
    this.priceMax = _knockout.observable(-1);
    this.setBuckets(props.result().indexes, props.activeIndex());

    // $(document).click(".mstInPage__bucket .filter-options-title", e => {
    //     $(e.target).closest(".mstInPage__bucket").toggleClass("active")
    // })

    props.result.subscribe(function (result) {
      return _this.setBuckets(result.indexes, props.activeIndex());
    });
    props.activeIndex.subscribe(function (index) {
      return _this.setBuckets(props.result().indexes, index);
    });
  };
  function waitForElementToDisplay(selector, callback, checkFrequencyInMs, timeoutInMs) {
    var startTimeInMs = Date.now();
    (function loopSearch() {
      if (document.querySelector(selector) != null) {
        callback();
        return;
      } else {
        setTimeout(function () {
          if (timeoutInMs && Date.now() - startTimeInMs > timeoutInMs) return;
          loopSearch();
        }, checkFrequencyInMs);
      }
    })();
  }
  return {
    SidebarView: SidebarView
  };
});
//# sourceMappingURL=SidebarView.js.map