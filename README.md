# Automated YouTube Videos to Wordpress Posts

To expand my Coderman [YouTube channel](https://www.youtube.com/coderman) reach, help more people and have an offsite archive of my YouTube videos content, I ventured into creating a Wordpress website. Originally I was copying URLs and making Wordpress posts each time I uploaded a new video, which meant my website was behind on the video content, from forgetting or not linking properly. I decided to create this script to automate the process. You can see its lightweight, no libraries or fluff. All it needs is Wordpress path for `wp-load.php` and a [YouTube API Key](https://console.cloud.google.com/apis/).

## Getting Started

Things you need to install and run the script.

1. Wordpress (of course)
2. [Google "YouTube" API key](https://console.cloud.google.com/apis/)
3. Set environment variable `YOUTUBE_API_KEY` , or put API key in script

Only config vars you need to be concerned with

```
$channel_id     = "QPNHmONj2B8Yp70I_RpE"; // YouTube channel ID
$max_results    = 5;
$auth_key       = ""; // Set api key here or in environment vars
$order          = "date";
```

## Contributing

Please read [CONTRIBUTING.md](https://gist.github.com/PurpleBooth/b24679402957c63ec426) for details on our code of conduct, and the process for submitting pull requests to us.

## Acknowledgments

* Wordpress for creating the blogging platform!
* YouTube for creating developer friendly APIs
* Open Source community for being awesome
