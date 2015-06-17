coffeescript-concat -o output.coffee scripts/UQC.coffee scripts/ContentHelper.coffee scripts/Likes.coffee
coffee -o . output.coffee
mv output.js assets/js/likes.js
rm output.coffee
