class UQC

	# this is from Brian's jsUtil.js => https://github.com/brian-c/js-util
	@bind = (scope, func, args) ->
		func = scope[func] if typeof func is "string"

		return ->
			func.apply scope, args or arguments_
	;

	@trim = (string) ->
		string.replace /^\s*|\s*$/g, ""
	;

	@stripHTML = (text) ->
		text.replace /<\/?[^>]+>/g, ""
	;
;