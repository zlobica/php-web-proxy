<!-- ### BEGIN PROXY NAVBAR ### -->
<?php $displayStyle = ($this->opts['navbar_sticky'] === TRUE) ? 'block' : 'none'; ?>
<script type="text/javascript" src="js/jquery.js"></script>
<div id="proxy_navbar" style="position:fixed;width:100%;background:white;border-width:0px;border-bottom:2px solid gray;margin:0px;left:0px;top:0px;z-index:10000000;display:<?= $displayStyle ?>;padding:0px;font-family:tahoma,verdana,arial,sans-serif;color:black;font-size:1em;font-style:normal;font-variant:normal;font-weight:normal;line-height:100%;">

<form method="post" action="<?= INDEX_FILE_NAME ?>" style="display:inline;">
<input type="hidden" name="action" value="new" />

<table width="100%" border="0">
	<tr>
		<td width="10%"><label for="proxy_url">Current URL:</label></td>
		<td width="80%"><input id="proxy_url" type="text" name="<?= URL_PARAM_NAME ?>" style="width:100%;" value="<?= $this->url ?>" /></td>
		<td width="10%">
			<input type="submit" value="Go" />
			<label>Sticky <input type="checkbox" id="proxy_navbar_sticky" <?php if ($this->opts['navbar_sticky'] === TRUE) echo ' checked="checked"'; ?>/></label>
		</td>
	</tr>
</table>

</form>

</div>

<script type="text/javascript">

var _hidden = <?= $this->opts['navbar_sticky'] ? 'false' : 'true' ?>;

$('#proxy_navbar').mousemove(function() {
	return false;
});

$(window).mousemove(function(e) {
	var posY = e.clientY;
	
	var navbar = $('#proxy_navbar');
	
	if (posY < 15 && _hidden) {
		navbar.slideDown('fast');
		_hidden = false;
	}
	else {
		if (posY > 50 && !_hidden && !isSticky()) {
			navbar.slideUp('fast');
			_hidden = true;
		}
	}
});

function isSticky() {
	return $('#proxy_navbar_sticky').get(0).checked;
}

</script>
<!-- ### END PROXY NAVBAR ### -->