// use ajax to get uid by uname, using felix API
function unam_to_uid()
{
	document.getElementById('qmsg').innerHTML = "查找中，請稍候…";
	var unam = document.getElementById('unam').value;
	var url = "http://uhunt.onlinejudge.org/api/uname2uid/"+encodeURIComponent(unam);
	document.getElementById('unam').value = unam;
	if (window.XMLHttpRequest)
	{
		ajax = new XMLHttpRequest();
	}
	else if (window.ActiveXObject)
	{
		ajax = new ActiveXObject("Microsoft.XMLHTTP");
	}
	ajax.onreadystatechange = unam_to_uid_cb;
	ajax.open("GET", url, true);
	ajax.send("");
	return false;
}

// ajax callback
function unam_to_uid_cb()
{
	if(ajax.readyState == 4)
	{
		document.getElementById('qmsg').innerHTML = "查找完成";
		console.log("resp code: "+ajax.status);
		if(ajax.status == 200)
		{
			var val = parseInt(ajax.responseText);
			if(val < 0)
			{
				val = 0;
			}
			document.getElementById('uid').value = val;
			return;
		}
	}
}

// show hidden hint
function show_hint(id)
{
	document.getElementById("h"+id).style.display = "block";
	document.getElementById("hh"+id).style.display = "none";
}

// show hidden type
function show_type(id)
{
	document.getElementById("t"+id).style.display = "block";
	document.getElementById("tt"+id).style.display = "none";
}
