<!-- ### BEGIN PROXY NAVBAR ### -->
<?php $displayStyle = ($this->opts['navbar_sticky'] === TRUE) ? 'block' : 'none'; ?>
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

var _scrolling = false;

document.getElementById('proxy_navbar').addEventListener('mousemove', function(e) {
	if (e.stopPropagation) { e.stopPropagation(); }
	e.cancelBubble = true;
}, false);

window.addEventListener('mousemove', function(e) {
	var posY = e.clientY;
	
	var navbar = document.getElementById('proxy_navbar');
	var height = navbar.offsetHeight;
	
	if (_scrolling === true) {
		return;
	}
	
	if (posY < 15 && navbar.style.display == 'none') {
		navbar.style.top = '-25px';
		navbar.style.display = 'block';
		scroller(navbar, 2, 0);
	}
	else {
		if (navbar.style.display == 'block' && !isSticky()) {
			scroller(navbar, -2, -height, function() { navbar.style.display = 'none'});
		}
	}
}, false);

function scroller(navbar, increment, target, complete_callback) {
	_scrolling = true;
	
	var top = parseInt(navbar.style.top);
	navbar.style.top = (top + increment) + 'px';
	
	if ((increment < 0 && top > target) || (increment > 0 && top < target)) {
		setTimeout(function() { scroller(navbar, increment, target, complete_callback); }, 10);
	}
	else {
		_scrolling = false;
		navbar.style.top = target + 'px';
		if (complete_callback) {
			complete_callback();
		}
	}
}

function isSticky() {
	return document.getElementById('proxy_navbar_sticky').checked;
}

</script>
<!-- ### END PROXY NAVBAR ### -->