<?php
/**
 * @package TSF_Extension_Manager\Classes
 */
namespace TSF_Extension_Manager;

defined( 'ABSPATH' ) or die;

/**
 * The SEO Framework - Extension Manager plugin
 * Copyright (C) 2018 Sybre Waaijer, CyberWire (https://cyberwire.nl/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published
 * by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Class TSF_Extension_Manager\HTML.
 *
 * Puts elements in common HTML wraps.
 * All functions are publically accessible by default.
 *
 * Importing this class for clean code is recommended.
 * @see <http://php.net/manual/en/language.namespaces.importing.php>
 *
 * @since 1.5.0
 * @access public
 * @final
 */
final class HTML {
	use Enclose_Stray_Private,
		Construct_Core_Static_Final;

	/**
	 * Wraps tooltip item in wrapper.
	 *
	 * @since 1.5.0
	 */
	static function wrap_inline_tooltip( $content, array $classes = [] ) {
		$classes[] = 'tsfem-tooltip-wrap';
		return vsprintf(
			'<span class="%s">%s</span>',
			[
				implode( ' ', $classes ),
				$content,
			]
		);
	}

	/**
	 * Makes tooltip item when titles exists.
	 *
	 * @since 1.5.0
	 *
	 * @param string $content    The content within the wrap. Must be escaped.
	 * @param string $title      The title displayed when JS is disabled.
	 *                           Also functions as tooltip (without HTML) if $title_html
	 *                           is omitted.
	 * @param string $title_html The definite tooltip, may contain HTML. Optional.
	 * @param array  $classes    The additional tooltip classes.
	 */
	static function make_inline_tooltip( $content, $title, $title_html = '', array $classes = [] ) {

		$title = \esc_attr( \wp_strip_all_tags( $title ) );
		$title_html = $title_html ? sprintf( 'data-desc="%s"', \esc_attr( \esc_html( $title_html ) ) ) : '';

		strlen( $title . $title_html ) and $classes[] = 'tsfem-tooltip-item';

		return vsprintf(
			'<span class="%s" title="%s" %s>%s</span>',
			[
				implode( ' ', $classes ),
				$title,
				$title_html,
				$content,
			]
		);
	}

	/**
	 * Makes an dropdown options list from input.
	 *
	 * @since 1.5.0
	 *
	 * @param array $options : {
	 *    'value' (string) <weak> The option value => 'title' (string) The option title,
	 * }
	 * @param string $selected The currently selected value.
	 * @return string The formatted options list.
	 */
	static function make_dropdown_option_list( array $options, $selected = '' ) {
		$out = '';
		$selected = (string) $selected;
		foreach ( $options as $value => $title ) {
			$value = \esc_attr( $value );
			$out .= sprintf(
				'<option value="%s"%s>%s</option>',
				$value,
				$value === $selected ? ' selected' : '',
				\esc_html( $title )
			);
		}
		return $out;
	}
}
