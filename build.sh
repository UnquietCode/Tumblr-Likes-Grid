coffeescript-concat -o output.coffee -I js js/Likes.coffee
coffee -o . output.coffee
mv output.js likes.js
rm output.coffee