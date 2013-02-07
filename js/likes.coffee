#= require Content


class @Likes
	@debug = false

	currentOffset = 0
	isScrolling = false
	isFinished = false


	# --- get likes from server  ---

	getLikes = (runs, etc) ->
		return if isFinished
		runs = runs or 1
		etc = etc or function() { }

		console.log "getting likes at offset " + currentOffset if @debug
		next = runs is 1 ? etc : -> getLikes(runs- 1, etc)

		$.ajax
			url: "?getData&offset=" + currentOffset
			success: (data) ->
				successForLikes(data)
				next()

			error: ->
				failForLikes()
				next()

		currentOffset += 20
		console.log "finished" if @debug
	;

	successForLikes = (data) ->
		console.log "got data" if @debug
		data = JSON.parse(data)

		# if less than batch size, we must have run out!
		if currentOffset >= data.response.liked_count
			console.log "We're finished!" if @debug
			isFinished = true
			$("#loading").fadeOut 800

		setContent(data.response.liked_posts)
	;

	failForLikes = (data) ->
		console.log "failure"
		console.log data
	;

	# --- user info in header ---

	setHeaderInfo = ->
		$.ajax
			url: "?getUserName"
			success: (data) ->
				successForUserInfo(data)

			error: (data) ->
				failForUserInfo(data)
	;

	successForUserInfo = (data) ->
		console.log "got user data" if @debug
		data = JSON.parse(data)
		setHeaderInfoComplete(data.response.user.likes, data.response.user.name, data.response.user.blogs[0].url)
	;

	failForUserInfo = (data) ->
		console.log "failure"
		console.log data
	;

	setHeaderInfoComplete = (likesCount, userName, primaryUrl) ->
		blog = $("#nav a.blog_title")
		text = "#{userName} &mdash; You like #{likesCount} posts!"
		blog.attr("href", primaryUrl)
		blog.html(text)
	;

	# --- infinite scroll ---

	scrollWatch = ->
		win = $(window)
		win.scroll ->
			return if isFinished

			if win.scrollTop() >= $(document).height() - win.height() - 200
				return if isScrolling
				isScrolling = true
				getLikes(1, ->
					isScrolling = false
				)
			;
		;
	;

	# - - -- - --- - -- - - -- - - - ---- -  -- - -- -- - - -  - - - ---- -- - - -

	@startUp = ->
		console.log "Tumblr Likes Grid, by Ben Fagin\nhttp://life.unquietcode.com\n\n"
		setHeaderInfo()
		getLikes(2)
		scrollWatch()
	;