import { m } from 'motion/react';

const container = {
	hidden: {},
	show: {
		transition: { staggerChildren: 0.08 },
	},
};

const item = {
	hidden: { opacity: 0, y: 16 },
	show: { opacity: 1, y: 0, transition: { duration: 0.4, ease: 'easeOut' } },
};

/**
 * Animates a list of items in with a staggered entrance — common for
 * feature grids, pricing cards, testimonial lists. Expects `items` to
 * already be plain data (not JSX) so the wrapper controls stagger timing;
 * `renderItem` returns the markup for one entry.
 */
export default function StaggerList( { items, renderItem, as = 'div', itemAs = 'div', className, itemClassName } ) {
	const Container = m[ as ] || m.div;
	const Item = m[ itemAs ] || m.div;

	return (
		<Container
			className={ className }
			variants={ container }
			initial="hidden"
			whileInView="show"
			viewport={ { once: true, amount: 0.2 } }
		>
			{ items.map( ( entry, i ) => (
				<Item className={ itemClassName } variants={ item } key={ entry.key ?? i }>
					{ renderItem( entry, i ) }
				</Item>
			) ) }
		</Container>
	);
}
