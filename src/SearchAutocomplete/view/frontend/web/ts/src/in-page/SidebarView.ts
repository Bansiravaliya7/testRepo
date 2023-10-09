import { Bucket, BucketItem, IndexResult, Result } from "../types"
import _ from "underscore"
import ko from "knockout"
import $ from "jquery"
import "Mirasvit_SearchAutocomplete/js/lib/jquery.ui.slider"

interface Props {
    result: KnockoutObservable<Result>
    activeIndex: KnockoutObservable<string>
    filterList: KnockoutObservable<Map<string, string[]>>
}

interface SelectableBucketItem extends BucketItem {
    select: () => void
    isActive: boolean
}

interface SelectableBucket extends Bucket {
    code: string
    label: string
    items: SelectableBucketItem[]
    isExpanded: boolean
    expand: () => void
}

const SliderSelector = "[data-slider='price']"

export class SidebarView {
    props: Props

    buckets: KnockoutObservableArray<SelectableBucket>
    activeBuckets: KnockoutObservableArray<SelectableBucket>
    collapses: KnockoutObservableArray<string>

    priceFrom: KnockoutObservable<number>
    priceTo: KnockoutObservable<number>
    priceMin: KnockoutObservable<number>
    priceMax: KnockoutObservable<number>

    constructor(props: Props) {
        this.props = props
        this.buckets = ko.observableArray([])
        this.activeBuckets = ko.observableArray([])
        this.collapses = ko.observableArray([])
        this.priceFrom = ko.observable(-1)
        this.priceTo = ko.observable(-1)
        this.priceMin = ko.observable(-1)
        this.priceMax = ko.observable(-1)

        this.setBuckets(props.result().indexes, props.activeIndex())

        // $(document).click(".mstInPage__bucket .filter-options-title", e => {
        //     $(e.target).closest(".mstInPage__bucket").toggleClass("active")
        // })

        props.result.subscribe(result => this.setBuckets(result.indexes, props.activeIndex()))
        props.activeIndex.subscribe(index => this.setBuckets(props.result().indexes, index))
    }

    setBuckets = (indexes: IndexResult[], indexIdentifier: string) => {
        let buckets: SelectableBucket[] = []
        let activeBuckets: SelectableBucket[] = []

        _.each(indexes, idx => {
            if (idx.identifier != indexIdentifier) {
                return
            }

            _.each(idx.buckets, bucket => {
                let bucketItems: SelectableBucketItem[] = []
                let activeBucketItems: SelectableBucketItem[] = []

                if (bucket.code == "price") {
                    const min = bucket.min as number
                    const max = bucket.max as number

                    if (this.priceMin() == -1 || this.priceMin() > min) {
                        this.priceMin(min)
                    }
                    if (this.priceMax() == -1 || this.priceMax() < max) {
                        this.priceMax(max)
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

                _.each(bucket.items, item => {
                    const filter = this.props.filterList().get(bucket.code)
                    let state = filter && filter.indexOf(item.key) >= 0 ? true : false

                    if (state) {
                        activeBucketItems.push({
                            ...item,
                            isActive: state,
                            select:   () => this.selectItem(bucket.code, item.key),
                        })
                    }

                    bucketItems.push({
                        ...item,
                        isActive: state,
                        select:   () => this.selectItem(bucket.code, item.key),
                    })
                })

                if (bucketItems.length > 0 || bucket.code == "price") {
                    buckets.push({
                        ...bucket,
                        items:      bucketItems,
                        isExpanded: !_.include(this.collapses(), bucket.code),
                        expand:     () => {
                            let collapses = _.clone(this.collapses())
                            if (_.include(collapses, bucket.code)) {
                                collapses = _.without(collapses, bucket.code)
                            } else {
                                collapses.push(bucket.code)
                            }

                            this.collapses(collapses)

                            this.setBuckets(this.props.result().indexes, this.props.activeIndex())
                        },
                    })
                }

                if (activeBucketItems.length > 0) {
                    activeBuckets.push({
                        ...bucket,
                        items:      activeBucketItems,
                        isExpanded: true,
                        expand:     _.noop,
                    })
                }
            })
        })

        this.buckets(buckets)
        this.activeBuckets(activeBuckets)

        _.each(indexes, idx => {
            if (idx.identifier != indexIdentifier) {
                return
            }
            _.each(idx.buckets, bucket => {
                if (bucket.code == "price") {
                    waitForElementToDisplay(SliderSelector, () => {
                        $(SliderSelector).slider({
                            range:  true,
                            min:    this.priceMin(),
                            max:    this.priceMax(),
                            values: [
                                this.priceFrom() != -1 ? this.priceFrom() : this.priceMin(),
                                this.priceTo() != -1 ? this.priceTo() : this.priceMax(),
                            ],
                            slide:  (e, ui: any) => {
                                this.priceFrom(
                                    ui.values[0] !== null && ui.values[0] >= 0
                                        ? ui.values[0]
                                        : 0,
                                )

                                this.priceTo(
                                    ui.values[1] !== null && ui.values[1] >= 0
                                        ? ui.values[1]
                                        : 0,
                                )
                            },
                            change: (e, ui: any) => {
                                this.priceFrom(
                                    ui.values[0] !== null && ui.values[0] >= 0
                                        ? ui.values[0]
                                        : 0,
                                )

                                this.priceTo(
                                    ui.values[1] !== null && ui.values[1] >= 0
                                        ? ui.values[1]
                                        : 0,
                                )

                                this.selectItem("price", this.priceFrom() + "_" + this.priceTo())
                            },
                            step:   1,
                        })
                    }, 10, 10000)
                }
            })
        })
    }

    selectItem = (bucketCode: string, key: string) => {
        const map = this.props.filterList()

        if (bucketCode === "price") {
            const mapItem = map.get("price")

            if (mapItem) {
                const indexOf = mapItem.indexOf(key)
                if (indexOf >= 0) {
                    if (mapItem[indexOf] == key) {
                        return
                    }
                }

                map.set(bucketCode, [ key ])
                this.props.filterList(map)
            } else {
                map.set(bucketCode, [ key ])
                this.props.filterList(map)
            }
        } else {
            const mapItem = map.get(bucketCode)

            if (mapItem) {
                const indexOf = mapItem.indexOf(key)
                if (indexOf >= 0) {
                    mapItem.splice(indexOf, 1)
                    if (mapItem.length > 0) {
                        map.set(bucketCode, mapItem)
                    } else {
                        map.delete(bucketCode)
                    }
                } else {
                    mapItem.push(key)
                    map.set(bucketCode, mapItem)
                }
            } else {
                map.set(bucketCode, [ key ])
            }

            this.props.filterList(map)
        }
    }
}

function waitForElementToDisplay(selector, callback, checkFrequencyInMs, timeoutInMs) {
    var startTimeInMs = Date.now();
    (function loopSearch() {
        if (document.querySelector(selector) != null) {
            callback()
            return
        } else {
            setTimeout(function () {
                if (timeoutInMs && Date.now() - startTimeInMs > timeoutInMs)
                    return
                loopSearch()
            }, checkFrequencyInMs)
        }
    })()
}