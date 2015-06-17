#= require ContentHelper

class @Likes
	@debug = true
	@tumblr

	currentOffset = 0
	isScrolling = false
	isFinished = false


	# --- get likes from server  ---

	getLikes = (runs, etc) ->
		return if isFinished
		runs = runs or 1
		etc = etc or ->

		console.log "getting likes at offset " + currentOffset if @debug
		next = if runs is 1 then etc else -> getLikes(runs- 1, etc)

		# --- GET /v2/user/likes ---
		Likes.tumblr.get "https://api.tumblr.com/v2/user/likes"
			.done (data) ->
				console.log "200 OK /v2/user/likes" if @debug
				console.log data
				successForLikes(data)
				next()
			.fail (err) ->
				console.log err if @debug
				failForLikes()
				next()

		currentOffset += 20
		console.log "finished" if @debug
	;

	successForLikes = (data) ->
		console.log "got data" if @debug

		# if less than batch size, we must have run out!
		if currentOffset >= data.response.liked_count
			console.log "We're finished!" if @debug
			isFinished = true
			$("#loading").fadeOut 800

		ContentHelper.setContent(data.response.liked_posts)
	;

	failForLikes = (data) ->
		console.log "failure"
		console.log data
	;

	# --- user info in header ---

	setHeaderInfo = () ->
		# --- get user info ---
		Likes.tumblr.get "https://api.tumblr.com/v2/user/info"
			.done (data) ->
				console.log "200 OK /v2/user/info" if @debug
				successForUserInfo(data)
			.fail (err) ->
				console.log err if @debug
				failForUserInfo(data)
	;

	successForUserInfo = (data) ->
		console.log "got user data" if @debug
		console.log data
		setHeaderInfoComplete(data.response.user.likes, data.response.user.name, data.response.user.blogs[0].url)
	;

	failForUserInfo = (data) ->
		console.log "GET /v2/user/info fail"
		console.log data
	;

	setHeaderInfoComplete = (likesCount, userName, primaryUrl) ->
		blog = $("#nav a.blog_title")
		text = "#{userName} &mdash; You like #{likesCount} posts!"
		blog.attr("href", primaryUrl)
		blog.html(text)
	;

	# --- unlike posts ---

	@unlike = (post) ->
		Likes.tumblr.post "https://api.tumblr.com/v2/user/unlike", {"id": post.id, "reblog_key": post.key}
			.done (data) ->
				successForUnlike(post.id, data)
			.fail (err) ->
				failForUnlike(data)
	;

	successForUnlike = (id, data) ->
		console.log("unliked post #{id}") if @debug
		$("#"+id+" a.remove").remove()
		$("#"+id+" img").remove()
		$("#"+id+" div.overprint").remove()
		$("#"+id+" div.play_overlay").remove()
		$("#"+id+" div.caption").remove()
		$("#"+id).addClass("removed")
	;

	failForUnlike = (data) ->
		alert("Sorry, but an error has occurred.")
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
		console.log "Initialize Oauth.js"
		OAuth.initialize 'eUqCWTW-6VpWWcOvj8edJ6aKNUo'

		console.log "Get cached credentials"
		Likes.tumblr = OAuth.create "tumblr"

		# TODO: redirect to '/' if user not login yet ---
		setHeaderInfo()
		getLikes(2)
		scrollWatch()

	;

window.Likes = Likes
