var uqc = {lib : {}};   // A bit premature, but why not?

// this is from Brian's jsUtil.js => https://github.com/brian-c/js-util
uqc.lib.bind = function(scope, func, args) {
	if (typeof func === 'string') { func = scope[func]; }

	return function() {
		func.apply(scope, args || arguments);
	};
};

uqc.lib.trim = function(string) {
	return string.replace(/^\s*|\s*$/g, "");
};

uqc.lib.stripHTML = function(text) {
    return text.replace(/<\/?[^>]+>/gi, '');
}

//////////////////////////////

var likes = {};

likes.debug = false;
likes.lastMonth = -1;
likes.currentOffset = 0;
likes.templateCache = {};
likes.MONTHS = ["January", "February", "March", "April", "May", "June","July","August","September","October","November","December"];


likes.mustache = {};
likes.mustache.renderTemplate = function(name, data) {
	var template = "Could not find template '"+ name +"'.";

	// is cached?
	if (!likes.templateCache[name]) {
		var ctx = {};

        	$.ajax({
                	url : 'templates/'+name+'.mustache',
               		async : false,
                	context : ctx,
                	success : function(data) {
                      		if (likes.debug) { console.log("retrieved template '" + name +"' from file."); }
				ctx.data = data;		
                	},
			failure : function() {
				if (likes.debug) { console.log("cold not retrieve template '" + name + "' from file."); }
			}
        	});
	
		if (ctx.data) {
			likes.templateCache[name] = ctx.data;
			template = ctx.data;
		}
	} else {
		template = likes.templateCache[name];
	}

	return Mustache.to_html(template, data);
}

likes.MONTHS_SHORT = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
likes.makeDate = function(month, day) {
	day += 1;
	var str = "";

	if (day == 1 || day == 21 || day == 31) {
		str = "st";
	} else if (day == 2 || day == 22) {
		str = "nd";
	} else if (day == 3 || day == 23) {
		str = "rd";
	} else {
		str = "th";
	}

	str = likes.MONTHS_SHORT[month] + ". " + day + "<sup>"+ str +"</sup>";
	return str; 
}

likes.makeTime = function(hours, minutes) {
	var pm = hours >= 12;	
	var str = (hours % 12) + ":" + (minutes < 10 ? "0" : "") + minutes + (pm ? "pm" : "am");
	return str;
};

likes.successForUserInfo = function(data) {
	if (likes.debug) { console.log("got user data"); }
    data = JSON.parse(data);
    
	likes.setHeaderInfoComplete(
        data.response.user.likes,
        data.response.user.name,
        data.response.user.blogs[0].url
    );
};

likes.failForUserInfo = function(data) {
	console.log("failure");
	console.log(data);
};

likes.setHeaderInfoComplete = function(likesCount, userName, primaryUrl) {
    var blog = $('#nav a.blog_title');
    var text = userName + " &mdash; You like " + likesCount + " posts!";
    blog.attr("href", primaryUrl);
    blog.html(text);
};

likes.setHeaderInfo = function() {
	$.ajax({
		url : "?getUserName",
		success : function(data) {
			likes.successForUserInfo(data); 
		},
		error : function(data) {
			likes.failForUserInfo(data);
		}
	});
    
};


likes.setContent = function(posts) {
    if (likes.debug) {
        console.log("received posts:");
        console.log(posts);
    }

	// loops through the posts
	var html = "";
    
	for (var i=0; i < posts.length; ++i) {
		var post = posts[i];
		
		// should we add a month divider?
		var date = new Date(post.timestamp * 1000);
		if (date.getMonth() !== likes.lastMonth) {
			likes.appendMonth('<div class="heading">'+ likes.MONTHS[date.getMonth()]+" "+date.getFullYear()+"</div>");
		}
	    
		// template context
		var ctx = likes.mustache.createContext();
		ctx.date = {
			year : date.getYear(),
			month : date.getMonth(),
			day : date.getDay(),
			shortForm : likes.makeDate(date.getMonth(), date.getDate()),
			time : likes.makeTime(date.getHours(), date.getMinutes())
		};
		ctx.id = post.id;
		ctx.type = post.type;
		ctx.url = post.post_url;   //post.link_url || post.post_url;
		ctx.user = post.blog_name;
		ctx.noteCount = post.note_count;
		ctx.caption = post.caption || "";		
		ctx.text = post.body || "";
		ctx.title = post.title || null;
		ctx.height = 125;


		// settings by type
		if (post.type === "video") {
            var thumbnail = {url:"#"};
            ctx.thumbnail = thumbnail;
            
			if (post.player && post.player.length > 0) {
				var raw = post.player[0].embed_code;
				var iStart = raw.indexOf("'poster=");
				var iEnd = raw.length-10;	// because we know it is towards the end
				
				// we found some frames
				if (iStart != -1) {
					var frameText = raw.substring(iStart+8, iEnd-1);
					var frames = frameText.split(",");
					for (var x = 0; x < frames.length; ++x) {
						frames[x] = { url : decodeURIComponent(frames[x]) };
					}

					ctx.frames = frames;
				}
			}

			thumbnail.url = post.thumbnail_url;
			thumbnail.height = post.thumbnail_height;
			thumbnail.width = post.thumbnail_width;
		} else if (post.type === "photo") {
            var thumbnail = {url:"#"};
            ctx.thumbnail = thumbnail;
                    
			if (post.photos.length > 0) {
				var sizes = post.photos[0].alt_sizes;
				var img = sizes.length-3 < 0 ? sizes.length-1 : sizes.length-3;
				img = sizes[img];
				thumbnail.url = img.url;
				thumbnail.height = img.height;
				thumbnail.width = img.width;
			}
		} else if (post.type === "quote") {
            ctx.text = post.text;
            ctx.source = post.source;
        } else if (post.type === "chat") {
            var chat = [];
            ctx.chat = chat;
            ctx.type = "conversation";
            
            for (var lcv=0; lcv < post.dialogue.length; ++lcv) {
                if (lcv % 2 == 0) {
                    chat[lcv] = {};
                    chat[lcv].first = post.dialogue[lcv];
                } else {
                    chat[lcv-1].second = post.dialogue[lcv];
                }
            }
        } else if (post.type === "answer") {
       		ctx.theQuestion = post.question || "";
            ctx.theAnswer = post.answer || "";
            ctx.theAsker = post.asking_name || "";
        } else if (post.type === "audio") {
            ctx.text = post.caption;
            var info = "";
            
            if (post.artist && post.artist.length > 0) {
                if (post.track_name && post.track_name.length > 0) {
                    info = post.artist + " - " + post.track_name;
                } else {
                    info = post.artist;
                }
            }
            
            if (post.album && post.album.length > 0) {
                info += "<br/>"+post.album;
            }
            
            ctx.info = info.length > 0 ? info : null;
            
            if (post.album_art && post.album_art.length > 0) {
                ctx.thumbnail = post.album_art;          
            }
        }

        // thumbnail dimensions
		if (thumbnail && thumbnail.height && thumbnail.height < ctx.height) {
            if (thumbnail.height < likes.props.MIN_HEIGHT) {
                ctx.height = likes.props.MIN_HEIGHT;
            } else {
                ctx.height = thumbnail.height;
            }
		}

        // strip html from text
        //ctx.text = uqc.lib.stripHTML(ctx.text);
        ctx.text = $('<div>'+ctx.text+'</div>').text();
       
        // if title is too big, truncate it
        if (ctx.title && ctx.title.length > 210) {
            ctx.title = ctx.title.substring(0, 210);
        }
       
        // if text is too big, truncate it
        if (ctx.text.length > 180) {
            ctx.text = ctx.text.substring(0, 180) + " [...]";
        }
 
		likes.lastMonth = date.getMonth();
 		likes.append(likes.mustache.renderTemplate("node", ctx));
	}
};

likes.appendMonth = function(html) {
    // create a new container, add a date object
	var container = $('<div class="container">');
	container.append(html);
    
    for (var i=0; i < likes.props.COLUMNS; ++i) {
        container.append($('<ul class="column">'));
    }
    
	$('.grid').append(container);
};

likes.props = {};
likes.props.COLUMNS = 8;

likes.append = function(html) {
	var nodes = $(".grid .container:last a.brick");
	//var cur = nodes.length + 1;
    var col = (nodes.length) % likes.props.COLUMNS;
    
    var node = $('<li class="stack" style="display:none;">');
    node.append(html);
    $($(".grid .container:last ul.column")[col]).append(node);
    node.fadeIn(600);
};

//likes.mustache = {};
likes.mustache.renderPartial = function(partial, render) {
	partial = uqc.lib.trim(render(partial));
	return likes.mustache.renderTemplate(partial, this);
};

likes.mustache.createContext = function() {
	// setup default stuff
	return {
		dynamicPartial : function() { return likes.mustache.renderPartial; }
	}
};

likes.fadeIn = function(nodeId) {
	var node = $("#"+nodeId);
};

likes.successForLikes = function(data) {
	if (likes.debug) { console.log("got data"); }
	data = JSON.parse(data);

	// if less than batch size, we must have run out!
	if (likes.currentOffset >= data.response.liked_count) {
		if (likes.debug) { console.log("We're finished!"); }
		likes.isFinished = true;
        $('#loading').fadeOut(800);
	}
	
	likes.setContent(data.response.liked_posts);
};

likes.failForLikes = function(data) {
	console.log("failure");
	console.log(data);
};

likes.getLikes = function(runs, etc) {
	if (likes.isFinished) { return; }

	runs = runs || 1;
	etc = etc || function() { };

	if (likes.debug) { console.log("getting likes at offset " + likes.currentOffset); }
	var next = runs == 1 ? etc : function(){likes.getLikes(runs-1, etc);};

	$.ajax({
		url : "?getData&offset="+likes.currentOffset,
		success : function(data) {
			likes.successForLikes(data); 
			next();
		},
		error : function() {
			likes.failForLikes();
			next();
		},
	});

	likes.currentOffset += 20;
	if (likes.debug) { console.log("finished"); }
};

likes.isScrolling = false;
likes.isFinished = false;

likes.scrollWatch = function() {
	var win = $(window);
	win.scroll(function() {
		if (likes.isFinished) { return; }

		if (win.scrollTop() >= $(document).height() - win.height() - 200) {
			if (likes.isScrolling) { return; }
			
			likes.isScrolling = true;

			likes.getLikes(1, function() {
				likes.isScrolling = false;
			});
		}
	}); 
};

likes.startUp = function() {
    console.log("Tumblr Likes Grid, by Ben Fagin\nhttp://life.unquietcode.com\n\n");
    
    likes.setHeaderInfo();
    likes.getLikes(2);
	likes.scrollWatch();
};

