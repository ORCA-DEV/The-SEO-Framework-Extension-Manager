<?php
/**
 * @package TSF_Extension_Manager\Extension\Focus\Views
 * @subpackage TSF_Extension_Manager\Inpost\Audit\Templates;
 */
namespace TSF_Extension_Manager\Extension\Focus;

/**
 * @package TSF_Extension_Manager\Classes
 */
use \TSF_Extension_Manager\InpostGUI as InpostGUI;

defined( 'ABSPATH' ) and InpostGUI::verify( $_secret ) or die;

?>
<script type="text/html" id="tmpl-tsfem-e-focus-nofocus">
	<div><span><?php esc_html_e( 'No elements are found that support this feature.', 'the-seo-framework-extension-manager' ); ?></span></div>
</script>
<?php
