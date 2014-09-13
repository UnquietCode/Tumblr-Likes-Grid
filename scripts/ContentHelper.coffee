#=require UQC


class ContentHelper
	COLUMNS = 8
	MIN_HEIGHT = null

	MONTHS = [
		"January", "February", "March", "April", "May", "June",
		"July", "August", "September", "October", "November", "December"
	]

	MONTHS_SHORT = [
		"Jan", "Feb", "Mar", "Apr", "May", "Jun",
		"Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
	]

	@debug = false
	lastMonth = -1
	templateCache = {}
	sections = {}

	@setContent = (posts) ->
		if @debug
			console.log "received posts:"
			console.log posts
		#posts.sort((a, b) -> b.timestamp - a.timestamp)		

		for post in posts
			# should we add a month divider?
			date = new Date(post.timestamp * 1000)
			sectionName = "#{date.getYear()}:#{date.getMonth()}"

			if date.getMonth() isnt lastMonth
				appendMonth("<div class=\"heading\">#{MONTHS[date.getMonth()]} #{date.getFullYear()}</div>", sectionName)

			#templating
			ctx = createContext()
			ctx.date =
				year: date.getYear()
				month: date.getMonth()
				day: date.getDay()
				shortForm: makeDate(date.getMonth(), date.getDate())
				time: makeTime(date.getHours(), date.getMinutes())

			ctx.id = post.id
			ctx.key = post.reblog_key
			ctx.type = post.type
			ctx.url = post.post_url #post.link_url || post.post_url;
			ctx.user = post.blog_name
			ctx.noteCount = post.note_count
			ctx.caption = post.caption or ""
			ctx.text = post.body or ""
			ctx.title = post.title or null
			ctx.height = 125

			switch post.type
				when "video"  then setContextForVideo(post, ctx)
				when "audio"  then setContextForAudio(post, ctx)
				when "photo"  then setContextForPhoto(post, ctx)
				when "quote"  then setContextForQuote(post, ctx)
				when "chat"   then setContextForChat(post, ctx)
				when "answer" then setContextForAnswer(post, ctx)

			# thumbnail dimensions
			thumbnail = ctx.thumbnail

			if thumbnail and thumbnail.height and thumbnail.height < ctx.height
				if thumbnail.height < MIN_HEIGHT
					ctx.height = MIN_HEIGHT
				else
					ctx.height = thumbnail.height

			# strip html from text
			#ctx.text = uqc.lib.stripHTML(ctx.text);
			ctx.text = $("<div>" + ctx.text + "</div>").text()

			# if title is too big, truncate it
			ctx.title = ctx.title.substring(0, 210) if ctx.title and ctx.title.length > 210

			# if text is too big, truncate it
			ctx.text = ctx.text.substring(0, 180) + " [...]" if ctx.text.length > 180
			lastMonth = date.getMonth()
			append(renderTemplate("node", ctx), sectionName)
		;
	;

	renderTemplate = (name, data) ->
		template = "Could not find template '#{name}'."

		# is cached?
		unless templateCache[name]
			ctx = {}

			$.ajax
				url: "templates/#{name}.mustache"
				async: false
				context: ctx

				success: (data) ->
					console.log "retrieved template '#{name}' from file." if @debug
					ctx.data = data

				failure: ->
					console.log "cold not retrieve template '#{name}' from file." if @debug

			if ctx.data
				templateCache[name] = ctx.data
				template = ctx.data
		else
			template = templateCache[name]

		Mustache.to_html(template, data)
	;

	renderPartial = (partial, render) ->
		partial = UQC.trim(render(partial))
		renderTemplate(partial, this)
	;

	createContext = ->
		{
			dynamicPartial: -> renderPartial
		}
	;

	# --- content methods ---

	setContextForChat = (post, ctx) ->
		ctx.type = "conversation"
		ctx.chat = []
		chat = ctx.chat
		lcv = 0

		while lcv < post.dialogue.length
			if lcv % 2 is 0
				chat[lcv] = {}
				chat[lcv].first = post.dialogue[lcv]
			else
				chat[lcv - 1].second = post.dialogue[lcv]
			++lcv
		;
	;

	setContextForAnswer = (post, ctx) ->
		ctx.theQuestion = post.question or ""
		ctx.theAnswer = post.answer or ""
		ctx.theAsker = post.asking_name or ""
	;

	setContextForQuote = (post, ctx) ->
		ctx.text = post.text
		ctx.source = post.source
	;

	setContextForPhoto = (post, ctx) ->
		ctx.thumbnail = { url: "#" }
		thumbnail = ctx.thumbnail

		if post.photos.length > 0
			sizes = post.photos[0].alt_sizes
			img = if sizes.length-3 < 0 then sizes.length-1 else sizes.length-3
			img = sizes[img]
			thumbnail.url = img.url
			thumbnail.height = img.height
			thumbnail.width = img.width
		;
	;

	setContextForAudio = (post, ctx) ->
		ctx.text = post.caption
		info = ""

		if post.artist and post.artist.length > 0
			if post.track_name and post.track_name.length > 0
				info = "#{post.artist} - #{post.track_name}"
			else
				info = post.artist
		;

		info += "<br/>" + post.album  if post.album and post.album.length > 0
		ctx.info = if info.length > 0 then info else null
		ctx.thumbnail = post.album_art if post.album_art and post.album_art.length > 0
	;

	setContextForVideo = (post, ctx) ->
		thumbnail = { url: "#" }

		if post.player and post.player.length > 0
			raw = post.player[0].embed_code
			iStart = raw.indexOf("'poster=")
			iEnd = raw.length - 10 # because we know it is towards the end

			# we found some frames
			unless iStart is -1
				frameText = raw.substring(iStart + 8, iEnd - 1)
				frames = frameText.split(",")
				x = 0

				while x < frames.length
					frames[x] = { url: decodeURIComponent(frames[x]) }
					++x

				ctx.frames = frames
			;
		;

		thumbnail.url = post.thumbnail_url
		thumbnail.height = post.thumbnail_height
		thumbnail.width = post.thumbnail_width
		ctx.thumbnail = thumbnail
	;

	# --- helpers ---

	appendMonth = (html, sectionName) ->
		section = sections[sectionName]
		
		if not section
			# create a new container, add a date object
			container = $("<div class=\"container\">")
			container.append html
			
			i = 0

			while i < COLUMNS
				container.append $("<ul class=\"column\">")
				++i
			
			section = container
			sections[sectionName] = section
			$(".grid").append section
	;

	append = (html, sectionName) ->
		section = sections[sectionName]
		nodes = section.find("div.brick")
		col = (nodes.length) % COLUMNS
		node = $("<li class=\"stack\" style=\"display:none;\">")
		node.append(html)
		#$($(".grid .container:last ul.column")[col]).append(node)
		$(section.find("ul.column")[col]).append(node)
		node.fadeIn(600)
	;

	makeDate = (month, day) ->
		day += 1
		str = ""

		if day is 1 or day is 21 or day is 31
			str = "st"
		else if day is 2 or day is 22
			str = "nd"
		else if day is 3 or day is 23
			str = "rd"
		else
			str = "th"

		str = "#{MONTHS_SHORT[month]}. #{day}<sup>#{str}</sup>"
		return str
	;

	makeTime = (hours, minutes) ->
		pm = hours >= 12
		str = (hours % 12) + ":"
		str += (if minutes < 10 then "0" else "") + minutes
		str += if pm then "pm" else "am"
		return str
	;
