<?php
/**
 * Unit test for Orbit\Helper\Net\Wordpress\PostFetcher.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Orbit\Helper\Net\Wordpress\PostFetcher;
use Orbit\Helper\Net\HttpFetcher\FactoryFetcher;

class WordpressPostFetcherTest extends OrbitTestCase
{
    protected $fakeFetcher = NULL;

    public function setUp()
    {
        $this->fakeFetcher = FactoryFetcher::create('fake')->getInstance();
    }

    public function test_instance_should_ok()
    {
        $wpFetcher = new PostFetcher($this->fakeFetcher);
        $this->assertInstanceOf('Orbit\Helper\Net\Wordpress\PostFetcher', $wpFetcher);
    }

    public function test_get_2_posts_data_from_wordpress_should_ok()
    {
        $this->fakeFetcher->setResponse($this->dataJson2());
        $wpFetcher = new PostFetcher($this->fakeFetcher);
        $posts = $wpFetcher->getPosts();

        $this->assertSame(2, count($posts));
        $this->assertSame('https://v211.gotomalls.cool/blog/2016/11/7-tips-akhir-pekan-tenang/', $posts[0]->post_url);
        $this->assertSame('', (string)$posts[0]->image_url);
        $this->assertSame('7 Tips Akhir Pekan Tenang', (string)$posts[0]->title);
        $this->assertTrue(strlen($posts[1]->content) > 0);
        $this->assertTrue(strlen($posts[1]->post_date) > 0);
    }

    public function test_get_1_posts_data_from_wordpress_missing_image_property_no_default_image()
    {
        $this->fakeFetcher->setResponse($this->dataJson1MissingImagesProperty());
        $wpFetcher = new PostFetcher($this->fakeFetcher);
        $posts = $wpFetcher->getPosts();

        $this->assertSame('', (string)$posts[0]->image_url);
    }

    public function test_get_1_posts_data_from_wordpress_missing_image_property_using_default_image()
    {
        $this->fakeFetcher->setResponse($this->dataJson1MissingImagesProperty());
        $wpFetcher = new PostFetcher($this->fakeFetcher, ['default_image_url' => 'https://example.com/foo.jpg']);
        $posts = $wpFetcher->getPosts();

        $this->assertSame('https://example.com/foo.jpg', (string)$posts[0]->image_url);
    }


    public function test_get_posts_data_invalid_json()
    {
        $this->fakeFetcher->setResponse('{ this is not valid json }');
        $wpFetcher = new PostFetcher($this->fakeFetcher);

        $this->setExpectedException('Exception', 'Failed to decode JSON from wordpress');
        $posts = $wpFetcher->getPosts();
    }

protected function dataJson1MissingImagesProperty()
    {
return <<<EOF
[
    {
        "_links": {
            "about": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/types/post"
                }
            ],
            "author": [
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/users/2"
                }
            ],
            "collection": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/posts"
                }
            ],
            "curies": [
                {
                    "href": "https://api.w.org/{rel}",
                    "name": "wp",
                    "templated": true
                }
            ],
            "replies": [
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/comments?post=1832"
                }
            ],
            "self": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/posts/1832"
                }
            ],
            "version-history": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/posts/1832/revisions"
                }
            ],
            "wp:attachment": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/media?parent=1832"
                }
            ],
            "wp:featuredmedia": [
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/media/1834"
                }
            ],
            "wp:term": [
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/categories?post=1832",
                    "taxonomy": "category"
                },
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/tags?post=1832",
                    "taxonomy": "post_tag"
                }
            ]
        },
        "author": 2,
        "categories": [
            50,
            11,
            12
        ],
        "comment_status": "open",
        "content": {
            "protected": false,
            "rendered": "<p style=\"text-align: justify;\">Rutinitas harian kamu akan segera berakhir dan saatnya untuk menghadapi akhir pekan yang menyenangkan. Tapi terkadang di hari Senin, pernahkah kamu bertanya mengapa akhir pekan berlalu dengan cepat? Berikut adalah tips agar akhir pekan\u00a0kamu terasa seperti akhir pekan.</p>\n<p style=\"text-align: justify;\">\u00a0\u00a0<strong>Jangan\u00a0Tidur Larut Malam</strong></p>\n<div class=\"text_exposed_show\">\n<p style=\"text-align: justify;\">Bedagang di akhir pekan memang sangat menggiurkan. Mungkin ada pesta di hari Jumat\u00a0ataupun <em>event</em> kantor yang membuat kamu untuk tetap terjaga hingga larut malam. Alhasil, besok paginya kamu akan bangun kesiangan yang akan membuang waktumu di akhir pekan. Selain itu juga,\u00a0akan mengganggu kebiasaan tidur yang akhirnya akan membuatmu semakin lelah selama akhir pekan. Kalau kamu benar-benar lelah cobalah <em>power nap</em> sejenak di siang harinya.</p>\n<p style=\"text-align: justify;\"><img class=\"aligncenter wp-image-1835 \" src=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/maxresdefault-1024x576.jpg\" width=\"451\" height=\"291\" /></p>\n<p style=\"text-align: justify;\"><strong>Jangan Kerjakan Pekerjaan Kantor</strong></p>\n<p style=\"text-align: justify;\">Ketika jam di hari Jumat menunjuk kea rah angka 5, itu saatnya untuk berhenti bekerja dan perlahan beralih ke relax weekend mode. Tenang karena esok sudah hari Sabtu!</p>\n<p style=\"text-align: justify;\"><strong>Berhenti Sejenak Dari Sos-Med</strong></p>\n<p style=\"text-align: justify;\">Terus-terusan terhubung dengan sos-med dan internet akan membelah konsentrasimu dan mengalihkan fokus. Ini juga akan menguras energi kamu yang seharusnya digunakan untuk beristirahat bersama keluarga ataupun aktivitasmu di akhir pekan.</p>\n<p style=\"text-align: justify;\"><img class=\"size-medium wp-image-1836 aligncenter\" src=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/no-phones-300x300.jpg\" alt=\"no-phones\" width=\"300\" height=\"300\" srcset=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/no-phones-300x300.jpg 300w, https://blog.gotomalls.cool/wp-content/uploads/2016/11/no-phones-150x150.jpg 150w, https://blog.gotomalls.cool/wp-content/uploads/2016/11/no-phones-180x180.jpg 180w, https://blog.gotomalls.cool/wp-content/uploads/2016/11/no-phones.jpg 500w\" sizes=\"(max-width: 300px) 100vw, 300px\" /></p>\n<p style=\"text-align: justify;\"><strong>Rencanakan Kedepan Dengan Santai</strong></p>\n<p style=\"text-align: justify;\">Hidup perlu keseimbangan. Kamu dapat merencanakan jadwal minggu depan serta aktivitas apa saja yang akan kamu lakukan. Tapi cobalah rencanakan dengan <em>smart</em> agar akhir pekanmu tidak stress atau terlalu melelahkan.</p>\n<p style=\"text-align: justify;\"><strong>Keluar Dari Comfort Zone</strong></p>\n<p style=\"text-align: justify;\">Akhir pekan yang monoton dapat merusak <em>mood</em> istirahat akhir pekan berubah menjadi jenuh dan melelahkan. Cobalah untuk melakukan aktivitas yang baru yang belum pernah kamu lakukan. Misalnya dengan kegiatan olahraga baru. Variasikan akhir pekanmu agar jauh dari kejenuhan.</p>\n<p style=\"text-align: justify;\"><img class=\"aligncenter wp-image-1834 size-full\" src=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/relaxation-weekend-photos-47709.jpg\" alt=\"relaxation-weekend-photos-47709\" width=\"425\" height=\"282\" srcset=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/relaxation-weekend-photos-47709.jpg 425w, https://blog.gotomalls.cool/wp-content/uploads/2016/11/relaxation-weekend-photos-47709-300x199.jpg 300w\" sizes=\"(max-width: 425px) 100vw, 425px\" /></p>\n<p style=\"text-align: justify;\"><strong>The Art of Doing Nothing</strong></p>\n<p style=\"text-align: justify;\">Percaya atau tidak, terkadang kita perlu satu hari untuk tidak melakukan atau rencanakan kegiatan apapun. Cobalah paling tidak satu jam hanya untuk memanjakan diri kamu sendiri atau bahkan tidak melakukan apapun yang berarti.</p>\n<p style=\"text-align: justify;\"><strong>Jangan Tunda Pekerjaan di Akhir Pekan</strong></p>\n<p style=\"text-align: justify;\">Jika kamu lakukan ini, sudah menjadi jaminan akhir pekan kamu akan jenuh dan penuh beban. Cobalah untuk cicil pekerjaan di hari biasa agar kamu dapat istirahat penuh selama akhir pekan.</p>\n<p style=\"text-align: justify;\"><img class=\"size-medium wp-image-1833 aligncenter\" src=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/4c2161e35022465600eb30f08b7dd820-200x300.jpg\" alt=\"4c2161e35022465600eb30f08b7dd820\" width=\"200\" height=\"300\" srcset=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/4c2161e35022465600eb30f08b7dd820-200x300.jpg 200w, https://blog.gotomalls.cool/wp-content/uploads/2016/11/4c2161e35022465600eb30f08b7dd820.jpg 236w\" sizes=\"(max-width: 200px) 100vw, 200px\" /></p>\n</div>\n<p style=\"text-align: justify;\">Membaca buku juga akan memberikan sensasi relaksasi, dimana kamu dimanjakan dengan jalan cerita menarik ataupun pemikiran yang unik dan berbeda. Dapatkan potongan harga kalau kamu mau menghabiskan akhir pekan dengan membaca buku <a href=\"http://bit.ly/2fb9bKG\" target=\"_blank\">disini</a></p>\n"
        },
        "date": "2016-11-05T19:22:56",
        "date_gmt": "2016-11-05T11:22:56",
        "excerpt": {
            "protected": false,
            "rendered": "<p>Rutinitas harian kamu akan segera berakhir dan saatnya untuk menghadapi akhir pekan yang menyenangkan. Tapi terkadang di hari Senin, pernahkah kamu bertanya mengapa akhir pekan berlalu dengan cepat? Berikut adalah tips agar akhir pekan\u00a0kamu terasa seperti akhir pekan. \u00a0\u00a0Jangan\u00a0Tidur Larut Malam Bedagang di akhir pekan memang sangat menggiurkan. Mungkin ada pesta di hari Jumat\u00a0ataupun event&nbsp;</p>\n<p><a class=\"btn btn-style\" href=\"https://v211.gotomalls.cool/blog/2016/11/7-tips-akhir-pekan-tenang/\">Continue Reading</a></p>\n"
        },
        "featured_media": 1834,
        "format": "standard",
        "guid": {
            "rendered": "https://blog.gotomalls.com/?p=1832"
        },
        "id": 1832,
        "link": "https://v211.gotomalls.cool/blog/2016/11/7-tips-akhir-pekan-tenang/",
        "meta": {},
        "modified": "2016-11-05T19:22:56",
        "modified_gmt": "2016-11-05T11:22:56",
        "ping_status": "open",
        "slug": "7-tips-akhir-pekan-tenang",
        "sticky": false,
        "tags": [],
        "title": {
            "rendered": "7 Tips Akhir Pekan Tenang"
        },
        "type": "post"
    }
]
EOF;
    }

    protected function dataJson2()
    {
return <<<EOF
[
    {
        "_links": {
            "about": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/types/post"
                }
            ],
            "author": [
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/users/2"
                }
            ],
            "collection": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/posts"
                }
            ],
            "curies": [
                {
                    "href": "https://api.w.org/{rel}",
                    "name": "wp",
                    "templated": true
                }
            ],
            "replies": [
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/comments?post=1832"
                }
            ],
            "self": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/posts/1832"
                }
            ],
            "version-history": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/posts/1832/revisions"
                }
            ],
            "wp:attachment": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/media?parent=1832"
                }
            ],
            "wp:featuredmedia": [
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/media/1834"
                }
            ],
            "wp:term": [
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/categories?post=1832",
                    "taxonomy": "category"
                },
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/tags?post=1832",
                    "taxonomy": "post_tag"
                }
            ]
        },
        "author": 2,
        "better_featured_image": {
            "alt_text": "",
            "caption": "",
            "description": "",
            "id": 1834,
            "media_details": {
                "file": "2016/11/relaxation-weekend-photos-47709.jpg",
                "height": 282,
                "image_meta": {
                    "aperture": "0",
                    "camera": "",
                    "caption": "",
                    "copyright": "",
                    "created_timestamp": "0",
                    "credit": "",
                    "focal_length": "0",
                    "iso": "0",
                    "keywords": [],
                    "orientation": "0",
                    "shutter_speed": "0",
                    "title": ""
                },
                "sizes": {
                    "medium": {
                        "file": "relaxation-weekend-photos-47709-300x199.jpg",
                        "height": 199,
                        "mime-type": "image/jpeg",
                        "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/relaxation-weekend-photos-47709-300x199.jpg",
                        "width": 300
                    },
                    "newedge-small": {
                        "file": "relaxation-weekend-photos-47709-263x253.jpg",
                        "height": 253,
                        "mime-type": "image/jpeg",
                        "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/relaxation-weekend-photos-47709-263x253.jpg",
                        "width": 263
                    },
                    "shop_catalog": {
                        "file": "relaxation-weekend-photos-47709-300x282.jpg",
                        "height": 282,
                        "mime-type": "image/jpeg",
                        "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/relaxation-weekend-photos-47709-300x282.jpg",
                        "width": 300
                    },
                    "shop_thumbnail": {
                        "file": "relaxation-weekend-photos-47709-180x180.jpg",
                        "height": 180,
                        "mime-type": "image/jpeg",
                        "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/relaxation-weekend-photos-47709-180x180.jpg",
                        "width": 180
                    },
                    "thumbnail": {
                        "file": "relaxation-weekend-photos-47709-150x150.jpg",
                        "height": 150,
                        "mime-type": "image/jpeg",
                        "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/relaxation-weekend-photos-47709-150x150.jpg",
                        "width": 150
                    }
                },
                "width": 425
            },
            "media_type": "image",
            "post": 1832,
            "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/relaxation-weekend-photos-47709.jpg"
        },
        "categories": [
            50,
            11,
            12
        ],
        "comment_status": "open",
        "content": {
            "protected": false,
            "rendered": "<p style=\"text-align: justify;\">Rutinitas harian kamu akan segera berakhir dan saatnya untuk menghadapi akhir pekan yang menyenangkan. Tapi terkadang di hari Senin, pernahkah kamu bertanya mengapa akhir pekan berlalu dengan cepat? Berikut adalah tips agar akhir pekan\u00a0kamu terasa seperti akhir pekan.</p>\n<p style=\"text-align: justify;\">\u00a0\u00a0<strong>Jangan\u00a0Tidur Larut Malam</strong></p>\n<div class=\"text_exposed_show\">\n<p style=\"text-align: justify;\">Bedagang di akhir pekan memang sangat menggiurkan. Mungkin ada pesta di hari Jumat\u00a0ataupun <em>event</em> kantor yang membuat kamu untuk tetap terjaga hingga larut malam. Alhasil, besok paginya kamu akan bangun kesiangan yang akan membuang waktumu di akhir pekan. Selain itu juga,\u00a0akan mengganggu kebiasaan tidur yang akhirnya akan membuatmu semakin lelah selama akhir pekan. Kalau kamu benar-benar lelah cobalah <em>power nap</em> sejenak di siang harinya.</p>\n<p style=\"text-align: justify;\"><img class=\"aligncenter wp-image-1835 \" src=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/maxresdefault-1024x576.jpg\" width=\"451\" height=\"291\" /></p>\n<p style=\"text-align: justify;\"><strong>Jangan Kerjakan Pekerjaan Kantor</strong></p>\n<p style=\"text-align: justify;\">Ketika jam di hari Jumat menunjuk kea rah angka 5, itu saatnya untuk berhenti bekerja dan perlahan beralih ke relax weekend mode. Tenang karena esok sudah hari Sabtu!</p>\n<p style=\"text-align: justify;\"><strong>Berhenti Sejenak Dari Sos-Med</strong></p>\n<p style=\"text-align: justify;\">Terus-terusan terhubung dengan sos-med dan internet akan membelah konsentrasimu dan mengalihkan fokus. Ini juga akan menguras energi kamu yang seharusnya digunakan untuk beristirahat bersama keluarga ataupun aktivitasmu di akhir pekan.</p>\n<p style=\"text-align: justify;\"><img class=\"size-medium wp-image-1836 aligncenter\" src=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/no-phones-300x300.jpg\" alt=\"no-phones\" width=\"300\" height=\"300\" srcset=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/no-phones-300x300.jpg 300w, https://blog.gotomalls.cool/wp-content/uploads/2016/11/no-phones-150x150.jpg 150w, https://blog.gotomalls.cool/wp-content/uploads/2016/11/no-phones-180x180.jpg 180w, https://blog.gotomalls.cool/wp-content/uploads/2016/11/no-phones.jpg 500w\" sizes=\"(max-width: 300px) 100vw, 300px\" /></p>\n<p style=\"text-align: justify;\"><strong>Rencanakan Kedepan Dengan Santai</strong></p>\n<p style=\"text-align: justify;\">Hidup perlu keseimbangan. Kamu dapat merencanakan jadwal minggu depan serta aktivitas apa saja yang akan kamu lakukan. Tapi cobalah rencanakan dengan <em>smart</em> agar akhir pekanmu tidak stress atau terlalu melelahkan.</p>\n<p style=\"text-align: justify;\"><strong>Keluar Dari Comfort Zone</strong></p>\n<p style=\"text-align: justify;\">Akhir pekan yang monoton dapat merusak <em>mood</em> istirahat akhir pekan berubah menjadi jenuh dan melelahkan. Cobalah untuk melakukan aktivitas yang baru yang belum pernah kamu lakukan. Misalnya dengan kegiatan olahraga baru. Variasikan akhir pekanmu agar jauh dari kejenuhan.</p>\n<p style=\"text-align: justify;\"><img class=\"aligncenter wp-image-1834 size-full\" src=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/relaxation-weekend-photos-47709.jpg\" alt=\"relaxation-weekend-photos-47709\" width=\"425\" height=\"282\" srcset=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/relaxation-weekend-photos-47709.jpg 425w, https://blog.gotomalls.cool/wp-content/uploads/2016/11/relaxation-weekend-photos-47709-300x199.jpg 300w\" sizes=\"(max-width: 425px) 100vw, 425px\" /></p>\n<p style=\"text-align: justify;\"><strong>The Art of Doing Nothing</strong></p>\n<p style=\"text-align: justify;\">Percaya atau tidak, terkadang kita perlu satu hari untuk tidak melakukan atau rencanakan kegiatan apapun. Cobalah paling tidak satu jam hanya untuk memanjakan diri kamu sendiri atau bahkan tidak melakukan apapun yang berarti.</p>\n<p style=\"text-align: justify;\"><strong>Jangan Tunda Pekerjaan di Akhir Pekan</strong></p>\n<p style=\"text-align: justify;\">Jika kamu lakukan ini, sudah menjadi jaminan akhir pekan kamu akan jenuh dan penuh beban. Cobalah untuk cicil pekerjaan di hari biasa agar kamu dapat istirahat penuh selama akhir pekan.</p>\n<p style=\"text-align: justify;\"><img class=\"size-medium wp-image-1833 aligncenter\" src=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/4c2161e35022465600eb30f08b7dd820-200x300.jpg\" alt=\"4c2161e35022465600eb30f08b7dd820\" width=\"200\" height=\"300\" srcset=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/4c2161e35022465600eb30f08b7dd820-200x300.jpg 200w, https://blog.gotomalls.cool/wp-content/uploads/2016/11/4c2161e35022465600eb30f08b7dd820.jpg 236w\" sizes=\"(max-width: 200px) 100vw, 200px\" /></p>\n</div>\n<p style=\"text-align: justify;\">Membaca buku juga akan memberikan sensasi relaksasi, dimana kamu dimanjakan dengan jalan cerita menarik ataupun pemikiran yang unik dan berbeda. Dapatkan potongan harga kalau kamu mau menghabiskan akhir pekan dengan membaca buku <a href=\"http://bit.ly/2fb9bKG\" target=\"_blank\">disini</a></p>\n"
        },
        "date": "2016-11-05T19:22:56",
        "date_gmt": "2016-11-05T11:22:56",
        "excerpt": {
            "protected": false,
            "rendered": "<p>Rutinitas harian kamu akan segera berakhir dan saatnya untuk menghadapi akhir pekan yang menyenangkan. Tapi terkadang di hari Senin, pernahkah kamu bertanya mengapa akhir pekan berlalu dengan cepat? Berikut adalah tips agar akhir pekan\u00a0kamu terasa seperti akhir pekan. \u00a0\u00a0Jangan\u00a0Tidur Larut Malam Bedagang di akhir pekan memang sangat menggiurkan. Mungkin ada pesta di hari Jumat\u00a0ataupun event&nbsp;</p>\n<p><a class=\"btn btn-style\" href=\"https://v211.gotomalls.cool/blog/2016/11/7-tips-akhir-pekan-tenang/\">Continue Reading</a></p>\n"
        },
        "featured_media": 1834,
        "format": "standard",
        "guid": {
            "rendered": "https://blog.gotomalls.com/?p=1832"
        },
        "id": 1832,
        "link": "https://v211.gotomalls.cool/blog/2016/11/7-tips-akhir-pekan-tenang/",
        "meta": {},
        "modified": "2016-11-05T19:22:56",
        "modified_gmt": "2016-11-05T11:22:56",
        "ping_status": "open",
        "slug": "7-tips-akhir-pekan-tenang",
        "sticky": false,
        "tags": [],
        "title": {
            "rendered": "7 Tips Akhir Pekan Tenang"
        },
        "type": "post"
    },
    {
        "_links": {
            "about": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/types/post"
                }
            ],
            "author": [
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/users/2"
                }
            ],
            "collection": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/posts"
                }
            ],
            "curies": [
                {
                    "href": "https://api.w.org/{rel}",
                    "name": "wp",
                    "templated": true
                }
            ],
            "replies": [
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/comments?post=1825"
                }
            ],
            "self": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/posts/1825"
                }
            ],
            "version-history": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/posts/1825/revisions"
                }
            ],
            "wp:attachment": [
                {
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/media?parent=1825"
                }
            ],
            "wp:featuredmedia": [
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/media/1826"
                }
            ],
            "wp:term": [
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/categories?post=1825",
                    "taxonomy": "category"
                },
                {
                    "embeddable": true,
                    "href": "https://v211.gotomalls.cool/blog/wp-json/wp/v2/tags?post=1825",
                    "taxonomy": "post_tag"
                }
            ]
        },
        "author": 2,
        "better_featured_image": {
            "alt_text": "",
            "caption": "",
            "description": "",
            "id": 1826,
            "media_details": {
                "file": "2016/11/top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462.jpeg",
                "height": 462,
                "image_meta": {
                    "aperture": "0",
                    "camera": "",
                    "caption": "",
                    "copyright": "",
                    "created_timestamp": "0",
                    "credit": "",
                    "focal_length": "0",
                    "iso": "0",
                    "keywords": [],
                    "orientation": "0",
                    "shutter_speed": "0",
                    "title": ""
                },
                "sizes": {
                    "medium": {
                        "file": "top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-300x225.jpeg",
                        "height": 225,
                        "mime-type": "image/jpeg",
                        "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-300x225.jpeg",
                        "width": 300
                    },
                    "newedge-small": {
                        "file": "top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-263x253.jpeg",
                        "height": 253,
                        "mime-type": "image/jpeg",
                        "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-263x253.jpeg",
                        "width": 263
                    },
                    "newedge-thumb": {
                        "file": "top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-555x462.jpeg",
                        "height": 462,
                        "mime-type": "image/jpeg",
                        "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-555x462.jpeg",
                        "width": 555
                    },
                    "shop_catalog": {
                        "file": "top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-300x300.jpeg",
                        "height": 300,
                        "mime-type": "image/jpeg",
                        "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-300x300.jpeg",
                        "width": 300
                    },
                    "shop_single": {
                        "file": "top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-600x462.jpeg",
                        "height": 462,
                        "mime-type": "image/jpeg",
                        "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-600x462.jpeg",
                        "width": 600
                    },
                    "shop_thumbnail": {
                        "file": "top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-180x180.jpeg",
                        "height": 180,
                        "mime-type": "image/jpeg",
                        "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-180x180.jpeg",
                        "width": 180
                    },
                    "thumbnail": {
                        "file": "top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-150x150.jpeg",
                        "height": 150,
                        "mime-type": "image/jpeg",
                        "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-150x150.jpeg",
                        "width": 150
                    }
                },
                "width": 616
            },
            "media_type": "image",
            "post": 1825,
            "source_url": "https://blog.gotomalls.cool/wp-content/uploads/2016/11/top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462.jpeg"
        },
        "categories": [
            12,
            30,
            39
        ],
        "comment_status": "open",
        "content": {
            "protected": false,
            "rendered": "<p style=\"text-align: justify;\">Besok tak terasa sudah akhir pekan lagi dan saatnya untuk merencanakan aktivitas apa saja yang akan kamu lakukan terutama ketika kamu pergi ke mall untuk berbelanja. Di bawah ini ada tips yang terbukti manjur untuk menjadi seorang\u00a0<em>smart shopper!</em></p>\n<div class=\"text_exposed_show\">\n<p style=\"text-align: justify;\"><strong>Buatlah Daftar Belanja</strong><br />\nDengan adanya daftar barang yang perlu kamu beli, daftar ini akan sangat membantu kamu untuk menghemat uang. Tapi pastikan juga barang-barang yang dalam daftar kamu ini adalah barang-barang yang benar-benar kamu perlukan bukan barang yang sifatnya sekunder.</p>\n<p style=\"text-align: justify;\"><img class=\"alignnone  wp-image-1826\" src=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/top-10-us-shopping-malls-gelleria.rend_.tccom_.616.462-300x225.jpeg\" alt=\"top-10-us-shopping-malls-gelleria-rend-tccom-616-462\" width=\"318\" height=\"233\" /> <img class=\"alignnone wp-image-1827 \" src=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/MixC.Shopping.Mall_.original.4922-1024x683.jpg\" width=\"358\" height=\"235\" /></p>\n<p style=\"text-align: justify;\"><strong>Tentukan Batas Pengeluaranmu</strong><br />\nBanyak orang yang ketika berbelanja melewati batas pengeluaran mereka yang akhirnya menjadi beban untuk hari-hari esoknya. Kamu harus punya batas pengeluaran untuk berbelanja dan pastikan untuk berhenti sebelum mencapai batas itu.</p>\n<p style=\"text-align: justify;\"><strong>Bayar Dengan Tunai</strong><br />\nMenurut riset, sekitar 20% hingga 50% dari kita menggunakan kartu kredit maupun kartu debit untuk membayar belanjaan kita. Tapi lebih baik jika membayar langsung secara tunai karena akan lebih jelas untuk kamu batas berbelanja kamu.</p>\n<p style=\"text-align: justify;\"><img class=\"alignnone wp-image-1830 size-full\" src=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/images.jpg\" width=\"259\" height=\"194\" /></p>\n<p style=\"text-align: justify;\"><strong>Batasi Waktumu Dalam Mall</strong><br />\nWaktu akan sangat tidak terasa ketika kamu dalam <em>shopping mode</em>, tetapi hindari juga mondar-mandir yang tidak ada gunanya. Gunakan waktu kamu dengan baik dan jangan sampai terbuang dengan sia-sia. Batasi waktumu di mall dan selalu <em>stick\u00a0with the plan!</em></p>\n<p style=\"text-align: justify;\"><strong>Belanja Sendirian</strong><br />\nLebih seringnya jika berbelanja dengan orang lain ataupun dengan rombongan, akan berujung belanja barang-barang yang sebenarnya kita belum perlukan. Baiknya berbelanja sendiri jika kamu memang ingin menjadi <em>smart shopper.</em></p>\n<p style=\"text-align: justify;\"><strong>Bertanyalah \u201cApa ini yang aku butuh?\u201d</strong><br />\nBanyak dari kita yang berbelanja secara impulsif. Agar menjadi seorang yang <em>smart shopper</em>, sering-seringlah untuk tanya diri sendiri sebelum membeli &#8220;apakah perlu membeli barang tersebut?&#8221;</p>\n<p style=\"text-align: justify;\"><strong>Jangan Gengsi Belanja Diskon<br />\n</strong>Kebanyakan orang mungkin malas belanja diskon di mall karena barangnya tidak terlalu trendi atau kadangkala\u00a0barang lama. Jangan salah, kamu bisa mengakalinya dengan belanja barang yang <em>everlasting</em>, yang selalu bisa di <em>mix &amp; match</em>, contohnya kemeja putih, atasan hitam, dress unik dan lainnya. Banyak orang menyukai diskon besar-besaran karena harga yang ditawarkan jauh dengan harga awal karena pergantian tren atau musim misalnya.</p>\n<p style=\"text-align: justify;\"><img class=\"alignnone wp-image-1829 \" src=\"https://blog.gotomalls.cool/wp-content/uploads/2016/11/mall-retail-flagshipphoto-1.jpg\" width=\"549\" height=\"414\" /></p>\n</div>\n"
        },
        "date": "2016-11-04T16:31:57",
        "date_gmt": "2016-11-04T08:31:57",
        "excerpt": {
            "protected": false,
            "rendered": "<p>Besok tak terasa sudah akhir pekan lagi dan saatnya untuk merencanakan aktivitas apa saja yang akan kamu lakukan terutama ketika kamu pergi ke mall untuk berbelanja. Di bawah ini ada tips yang terbukti manjur untuk menjadi seorang\u00a0smart shopper! Buatlah Daftar Belanja Dengan adanya daftar barang yang perlu kamu beli, daftar ini akan sangat membantu kamu&nbsp;</p>\n<p><a class=\"btn btn-style\" href=\"https://v211.gotomalls.cool/blog/2016/11/life-hack-fridays-tips-sebelum-weekend-ke-mall-ala-gotomalls/\">Continue Reading</a></p>\n"
        },
        "featured_media": 1826,
        "format": "standard",
        "guid": {
            "rendered": "https://blog.gotomalls.com/?p=1825"
        },
        "id": 1825,
        "link": "https://v211.gotomalls.cool/blog/2016/11/life-hack-fridays-tips-sebelum-weekend-ke-mall-ala-gotomalls/",
        "meta": {},
        "modified": "2016-11-04T16:31:57",
        "modified_gmt": "2016-11-04T08:31:57",
        "ping_status": "open",
        "slug": "life-hack-fridays-tips-sebelum-weekend-ke-mall-ala-gotomalls",
        "sticky": false,
        "tags": [],
        "title": {
            "rendered": "Life Hack Fridays : Tips Sebelum Weekend Ke Mall ala Gotomalls"
        },
        "type": "post"
    }
]
EOF;
    }
}