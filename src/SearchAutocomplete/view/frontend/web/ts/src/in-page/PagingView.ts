import { IndexResult, Paging, Result } from "../types"
import _ from "underscore"
import ko from "knockout"

interface Props {
    result: KnockoutObservable<Result>
    activeIndex: KnockoutObservable<string>
    page: KnockoutObservable<number>
}

interface SelectablePage extends Paging {
    code: string
    label: string
    select: () => void
    isActive: boolean
}

export class PagingView {
    props: Props
    pages: KnockoutObservableArray<SelectablePage>

    constructor(props: Props) {
        this.props = props
        this.pages = ko.observableArray([])

        this.setPages(props.result().indexes, props.activeIndex())

        props.result.subscribe(result => this.setPages(result.indexes, props.activeIndex()))
        props.activeIndex.subscribe(index => this.setPages(props.result().indexes, index))
        props.page.subscribe(page => this.setPages(props.result().indexes, props.activeIndex()))
    }

    setPages = (indexes: IndexResult[], indexIdentifier: string) => {
        let pages: SelectablePage[] = []

        _.each(indexes, idx => {
            if (idx.identifier != indexIdentifier) {
                return
            }
            const pageItems: any[] = []
            _.each(idx.pages, (item, idx) => {
                if (idx <= 8) {
                    pageItems.push({
                        ...item,
                        select: () => (item.isActive) ? "" : this.selectItem(item),
                    })
                }
            })

            idx.pages = pageItems
            this.pages(pageItems)
        })
    }

    selectItem = (item: SelectablePage) => {
        this.props.page(parseInt(item.label))
    }
}
