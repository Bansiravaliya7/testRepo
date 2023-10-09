import $ from "jquery"

interface Props {
    query: KnockoutObservable<string>
    visible: KnockoutObservable<boolean>
}

export class SearchBarView {
    props: Props

    constructor(props: Props) {
        this.props = props

        props.visible.subscribe(visible => {
            visible && setTimeout(() => {
                const interval = setInterval(() => {
                    const el = $("[type=search]")[0]
                    if (el) {
                        el.focus()
                        clearInterval(interval)
                    }
                }, 10)
            }, 10)
        })

        $(document).on("keyup", e => {
            if (e.key === "Escape") {
                if (props.query()) {
                    props.query("")
                }
            }
        })
    }
}
