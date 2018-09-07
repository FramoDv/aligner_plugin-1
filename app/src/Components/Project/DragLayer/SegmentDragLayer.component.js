import * as React from 'react'
import SegmentComponent from '../Row/Segment/Segment.component'

export default class SegmentDragLayer extends React.PureComponent {

    constructor(props) {
        super(props);
        this.state = {
            animate: false
        }
    }

    componentWillUnmount() {
    }

    getStyles = () =>{
        const transform = this.state.animate ? {
            transform: 'rotate(-3deg) scale(1.05)',
            WebkitTransform: 'rotate(-3deg) scale(1.05)',
        }: {};
        return {
            display: 'inline-block',
            cursor: 'grabbing',
            width: '100%',
            ...transform
        }
    };
    animate = () => {
        this.setState({
            animate: true
        })
    };

    render() {
        return (
            <div className="dragSegmentContainer" style={this.getStyles()}>
                <SegmentComponent {...this.props.item} />
            </div>
        )
    }

    componentDidMount() {
        setTimeout(this.animate,10);
    }
}
