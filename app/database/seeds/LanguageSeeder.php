<?php
class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Seeding master language data...');
        try {
            DB::table('languages')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }

        $isoLangs = '{
            "ab":{
                "name_long":"Abkhaz",
                "name_native":"аҧсуа",
                "status" : "inactive"
            },
            "aa":{
                "name_long":"Afar",
                "name_native":"Afaraf",
                "status" : "inactive"
            },
            "af":{
                "name_long":"Afrikaans",
                "name_native":"Afrikaans",
                "status" : "inactive"
            },
            "ak":{
                "name_long":"Akan",
                "name_native":"Akan",
                "status" : "inactive"
            },
            "sq":{
                "name_long":"Albanian",
                "name_native":"Shqip",
                "status" : "inactive"
            },
            "am":{
                "name_long":"Amharic",
                "name_native":"አማርኛ",
                "status" : "inactive"
            },
            "ar":{
                "name_long":"Arabic",
                "name_native":"العربية",
                "status" : "inactive"
            },
            "an":{
                "name_long":"Aragonese",
                "name_native":"Aragonés",
                "status" : "inactive"
            },
            "hy":{
                "name_long":"Armenian",
                "name_native":"Հայերեն",
                "status" : "inactive"
            },
            "as":{
                "name_long":"Assamese",
                "name_native":"অসমীয়া",
                "status" : "inactive"
            },
            "av":{
                "name_long":"Avaric",
                "name_native":"авар мацӀ, магӀарул мацӀ",
                "status" : "inactive"
            },
            "ae":{
                "name_long":"Avestan",
                "name_native":"avesta",
                "status" : "inactive"
            },
            "ay":{
                "name_long":"Aymara",
                "name_native":"aymar aru",
                "status" : "inactive"
            },
            "az":{
                "name_long":"Azerbaijani",
                "name_native":"azərbaycan dili",
                "status" : "inactive"
            },
            "bm":{
                "name_long":"Bambara",
                "name_native":"bamanankan",
                "status" : "inactive"
            },
            "ba":{
                "name_long":"Bashkir",
                "name_native":"башҡорт теле",
                "status" : "inactive"
            },
            "eu":{
                "name_long":"Basque",
                "name_native":"euskara, euskera",
                "status" : "inactive"
            },
            "be":{
                "name_long":"Belarusian",
                "name_native":"Беларуская",
                "status" : "inactive"
            },
            "bn":{
                "name_long":"Bengali",
                "name_native":"বাংলা",
                "status" : "inactive"
            },
            "bh":{
                "name_long":"Bihari",
                "name_native":"भोजपुरी",
                "status" : "inactive"
            },
            "bi":{
                "name_long":"Bislama",
                "name_native":"Bislama",
                "status" : "inactive"
            },
            "bs":{
                "name_long":"Bosnian",
                "name_native":"bosanski jezik",
                "status" : "inactive"
            },
            "br":{
                "name_long":"Breton",
                "name_native":"brezhoneg",
                "status" : "inactive"
            },
            "bg":{
                "name_long":"Bulgarian",
                "name_native":"български език",
                "status" : "inactive"
            },
            "my":{
                "name_long":"Burmese",
                "name_native":"ဗမာစာ",
                "status" : "inactive"
            },
            "ca":{
                "name_long":"Catalan; Valencian",
                "name_native":"Català",
                "status" : "inactive"
            },
            "ch":{
                "name_long":"Chamorro",
                "name_native":"Chamoru",
                "status" : "inactive"
            },
            "ce":{
                "name_long":"Chechen",
                "name_native":"нохчийн мотт",
                "status" : "inactive"
            },
            "ny":{
                "name_long":"Chichewa; Chewa; Nyanja",
                "name_native":"chiCheŵa, chinyanja",
                "status" : "inactive"
            },
            "zh":{
                "name_long":"Chinese",
                "name_native":"中文 (Zhōngwén), 汉语, 漢語",
                "status" : "active"
            },
            "cv":{
                "name_long":"Chuvash",
                "name_native":"чӑваш чӗлхи",
                "status" : "inactive"
            },
            "kw":{
                "name_long":"Cornish",
                "name_native":"Kernewek",
                "status" : "inactive"
            },
            "co":{
                "name_long":"Corsican",
                "name_native":"corsu, lingua corsa",
                "status" : "inactive"
            },
            "cr":{
                "name_long":"Cree",
                "name_native":"ᓀᐦᐃᔭᐍᐏᐣ",
                "status" : "inactive"
            },
            "hr":{
                "name_long":"Croatian",
                "name_native":"hrvatski",
                "status" : "inactive"
            },
            "cs":{
                "name_long":"Czech",
                "name_native":"česky, čeština",
                "status" : "inactive"
            },
            "da":{
                "name_long":"Danish",
                "name_native":"dansk",
                "status" : "inactive"
            },
            "dv":{
                "name_long":"Divehi; Dhivehi; Maldivian;",
                "name_native":"ދިވެހި",
                "status" : "inactive"
            },
            "nl":{
                "name_long":"Dutch",
                "name_native":"Nederlands, Vlaams",
                "status" : "inactive"
            },
            "en":{
                "name_long":"English",
                "name_native":"English",
                "status" : "active"
            },
            "eo":{
                "name_long":"Esperanto",
                "name_native":"Esperanto",
                "status" : "inactive"
            },
            "et":{
                "name_long":"Estonian",
                "name_native":"eesti, eesti keel",
                "status" : "inactive"
            },
            "ee":{
                "name_long":"Ewe",
                "name_native":"Eʋegbe",
                "status" : "inactive"
            },
            "fo":{
                "name_long":"Faroese",
                "name_native":"føroyskt",
                "status" : "inactive"
            },
            "fj":{
                "name_long":"Fijian",
                "name_native":"vosa Vakaviti",
                "status" : "inactive"
            },
            "fi":{
                "name_long":"Finnish",
                "name_native":"suomi, suomen kieli",
                "status" : "inactive"
            },
            "fr":{
                "name_long":"French",
                "name_native":"français, langue française",
                "status" : "inactive"
            },
            "ff":{
                "name_long":"Fula; Fulah; Pulaar; Pular",
                "name_native":"Fulfulde, Pulaar, Pular",
                "status" : "inactive"
            },
            "gl":{
                "name_long":"Galician",
                "name_native":"Galego",
                "status" : "inactive"
            },
            "ka":{
                "name_long":"Georgian",
                "name_native":"ქართული",
                "status" : "inactive"
            },
            "de":{
                "name_long":"German",
                "name_native":"Deutsch",
                "status" : "inactive"
            },
            "el":{
                "name_long":"Greek, Modern",
                "name_native":"Ελληνικά",
                "status" : "inactive"
            },
            "gn":{
                "name_long":"Guaraní",
                "name_native":"Avañeẽ",
                "status" : "inactive"
            },
            "gu":{
                "name_long":"Gujarati",
                "name_native":"ગુજરાતી",
                "status" : "inactive"
            },
            "ht":{
                "name_long":"Haitian; Haitian Creole",
                "name_native":"Kreyòl ayisyen",
                "status" : "inactive"
            },
            "ha":{
                "name_long":"Hausa",
                "name_native":"Hausa, هَوُسَ",
                "status" : "inactive"
            },
            "he":{
                "name_long":"Hebrew (modern)",
                "name_native":"עברית",
                "status" : "inactive"
            },
            "hz":{
                "name_long":"Herero",
                "name_native":"Otjiherero",
                "status" : "inactive"
            },
            "hi":{
                "name_long":"Hindi",
                "name_native":"हिन्दी, हिंदी",
                "status" : "inactive"
            },
            "ho":{
                "name_long":"Hiri Motu",
                "name_native":"Hiri Motu",
                "status" : "inactive"
            },
            "hu":{
                "name_long":"Hungarian",
                "name_native":"Magyar",
                "status" : "inactive"
            },
            "ia":{
                "name_long":"Interlingua",
                "name_native":"Interlingua",
                "status" : "inactive"
            },
            "id":{
                "name_long":"Indonesian",
                "name_native":"Bahasa Indonesia",
                "status" : "inactive"
            },
            "ie":{
                "name_long":"Interlingue",
                "name_native":"Originally called Occidental; then Interlingue after WWII",
                "status" : "inactive"
            },
            "ga":{
                "name_long":"Irish",
                "name_native":"Gaeilge",
                "status" : "inactive"
            },
            "ig":{
                "name_long":"Igbo",
                "name_native":"Asụsụ Igbo",
                "status" : "inactive"
            },
            "ik":{
                "name_long":"Inupiaq",
                "name_native":"Iñupiaq, Iñupiatun",
                "status" : "inactive"
            },
            "io":{
                "name_long":"Ido",
                "name_native":"Ido",
                "status" : "inactive"
            },
            "is":{
                "name_long":"Icelandic",
                "name_native":"Íslenska",
                "status" : "inactive"
            },
            "it":{
                "name_long":"Italian",
                "name_native":"Italiano",
                "status" : "inactive"
            },
            "iu":{
                "name_long":"Inuktitut",
                "name_native":"ᐃᓄᒃᑎᑐᑦ",
                "status" : "inactive"
            },
            "ja":{
                "name_long":"Japanese",
                "name_native":"日本語 (にほんご／にっぽんご)",
                "status" : "active"
            },
            "jv":{
                "name_long":"Javanese",
                "name_native":"basa Jawa",
                "status" : "inactive"
            },
            "kl":{
                "name_long":"Kalaallisut, Greenlandic",
                "name_native":"kalaallisut, kalaallit oqaasii",
                "status" : "inactive"
            },
            "kn":{
                "name_long":"Kannada",
                "name_native":"ಕನ್ನಡ",
                "status" : "inactive"
            },
            "kr":{
                "name_long":"Kanuri",
                "name_native":"Kanuri",
                "status" : "inactive"
            },
            "ks":{
                "name_long":"Kashmiri",
                "name_native":"कश्मीरी, كشميري‎",
                "status" : "inactive"
            },
            "kk":{
                "name_long":"Kazakh",
                "name_native":"Қазақ тілі",
                "status" : "inactive"
            },
            "km":{
                "name_long":"Khmer",
                "name_native":"ភាសាខ្មែរ",
                "status" : "inactive"
            },
            "ki":{
                "name_long":"Kikuyu, Gikuyu",
                "name_native":"Gĩkũyũ",
                "status" : "inactive"
            },
            "rw":{
                "name_long":"Kinyarwanda",
                "name_native":"Ikinyarwanda",
                "status" : "inactive"
            },
            "ky":{
                "name_long":"Kirghiz, Kyrgyz",
                "name_native":"кыргыз тили",
                "status" : "inactive"
            },
            "kv":{
                "name_long":"Komi",
                "name_native":"коми кыв",
                "status" : "inactive"
            },
            "kg":{
                "name_long":"Kongo",
                "name_native":"KiKongo",
                "status" : "inactive"
            },
            "ko":{
                "name_long":"Korean",
                "name_native":"한국어 (韓國語), 조선말 (朝鮮語)",
                "status" : "inactive"
            },
            "ku":{
                "name_long":"Kurdish",
                "name_native":"Kurdî, كوردی‎",
                "status" : "inactive"
            },
            "kj":{
                "name_long":"Kwanyama, Kuanyama",
                "name_native":"Kuanyama",
                "status" : "inactive"
            },
            "la":{
                "name_long":"Latin",
                "name_native":"latine, lingua latina",
                "status" : "inactive"
            },
            "lb":{
                "name_long":"Luxembourgish, Letzeburgesch",
                "name_native":"Lëtzebuergesch",
                "status" : "inactive"
            },
            "lg":{
                "name_long":"Luganda",
                "name_native":"Luganda",
                "status" : "inactive"
            },
            "li":{
                "name_long":"Limburgish, Limburgan, Limburger",
                "name_native":"Limburgs",
                "status" : "inactive"
            },
            "ln":{
                "name_long":"Lingala",
                "name_native":"Lingála",
                "status" : "inactive"
            },
            "lo":{
                "name_long":"Lao",
                "name_native":"ພາສາລາວ",
                "status" : "inactive"
            },
            "lt":{
                "name_long":"Lithuanian",
                "name_native":"lietuvių kalba",
                "status" : "inactive"
            },
            "lu":{
                "name_long":"Luba-Katanga",
                "name_native":"",
                "status" : "inactive"
            },
            "lv":{
                "name_long":"Latvian",
                "name_native":"latviešu valoda",
                "status" : "inactive"
            },
            "gv":{
                "name_long":"Manx",
                "name_native":"Gaelg, Gailck",
                "status" : "inactive"
            },
            "mk":{
                "name_long":"Macedonian",
                "name_native":"македонски јазик",
                "status" : "inactive"
            },
            "mg":{
                "name_long":"Malagasy",
                "name_native":"Malagasy fiteny",
                "status" : "inactive"
            },
            "ms":{
                "name_long":"Malay",
                "name_native":"bahasa Melayu, بهاس ملايو‎",
                "status" : "inactive"
            },
            "ml":{
                "name_long":"Malayalam",
                "name_native":"മലയാളം",
                "status" : "inactive"
            },
            "mt":{
                "name_long":"Maltese",
                "name_native":"Malti",
                "status" : "inactive"
            },
            "mi":{
                "name_long":"Māori",
                "name_native":"te reo Māori",
                "status" : "inactive"
            },
            "mr":{
                "name_long":"Marathi (Marāṭhī)",
                "name_native":"मराठी",
                "status" : "inactive"
            },
            "mh":{
                "name_long":"Marshallese",
                "name_native":"Kajin M̧ajeļ",
                "status" : "inactive"
            },
            "mn":{
                "name_long":"Mongolian",
                "name_native":"монгол",
                "status" : "inactive"
            },
            "na":{
                "name_long":"Nauru",
                "name_native":"Ekakairũ Naoero",
                "status" : "inactive"
            },
            "nv":{
                "name_long":"Navajo, Navaho",
                "name_native":"Diné bizaad, Dinékʼehǰí",
                "status" : "inactive"
            },
            "nb":{
                "name_long":"Norwegian Bokmål",
                "name_native":"Norsk bokmål",
                "status" : "inactive"
            },
            "nd":{
                "name_long":"North Ndebele",
                "name_native":"isiNdebele",
                "status" : "inactive"
            },
            "ne":{
                "name_long":"Nepali",
                "name_native":"नेपाली",
                "status" : "inactive"
            },
            "ng":{
                "name_long":"Ndonga",
                "name_native":"Owambo",
                "status" : "inactive"
            },
            "nn":{
                "name_long":"Norwegian Nynorsk",
                "name_native":"Norsk nynorsk",
                "status" : "inactive"
            },
            "no":{
                "name_long":"Norwegian",
                "name_native":"Norsk",
                "status" : "inactive"
            },
            "ii":{
                "name_long":"Nuosu",
                "name_native":"ꆈꌠ꒿ Nuosuhxop",
                "status" : "inactive"
            },
            "nr":{
                "name_long":"South Ndebele",
                "name_native":"isiNdebele",
                "status" : "inactive"
            },
            "oc":{
                "name_long":"Occitan",
                "name_native":"Occitan",
                "status" : "inactive"
            },
            "oj":{
                "name_long":"Ojibwe, Ojibwa",
                "name_native":"ᐊᓂᔑᓈᐯᒧᐎᓐ",
                "status" : "inactive"
            },
            "cu":{
                "name_long":"Old Church Slavonic, Church Slavic, Church Slavonic, Old Bulgarian, Old Slavonic",
                "name_native":"ѩзыкъ словѣньскъ",
                "status" : "inactive"
            },
            "om":{
                "name_long":"Oromo",
                "name_native":"Afaan Oromoo",
                "status" : "inactive"
            },
            "or":{
                "name_long":"Oriya",
                "name_native":"ଓଡ଼ିଆ",
                "status" : "inactive"
            },
            "os":{
                "name_long":"Ossetian, Ossetic",
                "name_native":"ирон æвзаг",
                "status" : "inactive"
            },
            "pa":{
                "name_long":"Panjabi, Punjabi",
                "name_native":"ਪੰਜਾਬੀ, پنجابی‎",
                "status" : "inactive"
            },
            "pi":{
                "name_long":"Pāli",
                "name_native":"पाऴि",
                "status" : "inactive"
            },
            "fa":{
                "name_long":"Persian",
                "name_native":"فارسی",
                "status" : "inactive"
            },
            "pl":{
                "name_long":"Polish",
                "name_native":"polski",
                "status" : "inactive"
            },
            "ps":{
                "name_long":"Pashto, Pushto",
                "name_native":"پښتو",
                "status" : "inactive"
            },
            "pt":{
                "name_long":"Portuguese",
                "name_native":"Português",
                "status" : "inactive"
            },
            "qu":{
                "name_long":"Quechua",
                "name_native":"Runa Simi, Kichwa",
                "status" : "inactive"
            },
            "rm":{
                "name_long":"Romansh",
                "name_native":"rumantsch grischun",
                "status" : "inactive"
            },
            "rn":{
                "name_long":"Kirundi",
                "name_native":"kiRundi",
                "status" : "inactive"
            },
            "ro":{
                "name_long":"Romanian, Moldavian, Moldovan",
                "name_native":"română",
                "status" : "inactive"
            },
            "ru":{
                "name_long":"Russian",
                "name_native":"русский язык",
                "status" : "inactive"
            },
            "sa":{
                "name_long":"Sanskrit (Saṁskṛta)",
                "name_native":"संस्कृतम्",
                "status" : "inactive"
            },
            "sc":{
                "name_long":"Sardinian",
                "name_native":"sardu",
                "status" : "inactive"
            },
            "sd":{
                "name_long":"Sindhi",
                "name_native":"सिन्धी, سنڌي، سندھی‎",
                "status" : "inactive"
            },
            "se":{
                "name_long":"Northern Sami",
                "name_native":"Davvisámegiella",
                "status" : "inactive"
            },
            "sm":{
                "name_long":"Samoan",
                "name_native":"gagana faa Samoa",
                "status" : "inactive"
            },
            "sg":{
                "name_long":"Sango",
                "name_native":"yângâ tî sängö",
                "status" : "inactive"
            },
            "sr":{
                "name_long":"Serbian",
                "name_native":"српски језик",
                "status" : "inactive"
            },
            "gd":{
                "name_long":"Scottish Gaelic; Gaelic",
                "name_native":"Gàidhlig",
                "status" : "inactive"
            },
            "sn":{
                "name_long":"Shona",
                "name_native":"chiShona",
                "status" : "inactive"
            },
            "si":{
                "name_long":"Sinhala, Sinhalese",
                "name_native":"සිංහල",
                "status" : "inactive"
            },
            "sk":{
                "name_long":"Slovak",
                "name_native":"slovenčina",
                "status" : "inactive"
            },
            "sl":{
                "name_long":"Slovene",
                "name_native":"slovenščina",
                "status" : "inactive"
            },
            "so":{
                "name_long":"Somali",
                "name_native":"Soomaaliga, af Soomaali",
                "status" : "inactive"
            },
            "st":{
                "name_long":"Southern Sotho",
                "name_native":"Sesotho",
                "status" : "inactive"
            },
            "es":{
                "name_long":"Spanish; Castilian",
                "name_native":"español, castellano",
                "status" : "inactive"
            },
            "su":{
                "name_long":"Sundanese",
                "name_native":"Basa Sunda",
                "status" : "inactive"
            },
            "sw":{
                "name_long":"Swahili",
                "name_native":"Kiswahili",
                "status" : "inactive"
            },
            "ss":{
                "name_long":"Swati",
                "name_native":"SiSwati",
                "status" : "inactive"
            },
            "sv":{
                "name_long":"Swedish",
                "name_native":"svenska",
                "status" : "inactive"
            },
            "ta":{
                "name_long":"Tamil",
                "name_native":"தமிழ்",
                "status" : "inactive"
            },
            "te":{
                "name_long":"Telugu",
                "name_native":"తెలుగు",
                "status" : "inactive"
            },
            "tg":{
                "name_long":"Tajik",
                "name_native":"тоҷикӣ, toğikī, تاجیکی‎",
                "status" : "inactive"
            },
            "th":{
                "name_long":"Thai",
                "name_native":"ไทย",
                "status" : "inactive"
            },
            "ti":{
                "name_long":"Tigrinya",
                "name_native":"ትግርኛ",
                "status" : "inactive"
            },
            "bo":{
                "name_long":"Tibetan Standard, Tibetan, Central",
                "name_native":"བོད་ཡིག",
                "status" : "inactive"
            },
            "tk":{
                "name_long":"Turkmen",
                "name_native":"Türkmen, Түркмен",
                "status" : "inactive"
            },
            "tl":{
                "name_long":"Tagalog",
                "name_native":"Wikang Tagalog, ᜏᜒᜃᜅ᜔ ᜆᜄᜎᜓᜄ᜔",
                "status" : "inactive"
            },
            "tn":{
                "name_long":"Tswana",
                "name_native":"Setswana",
                "status" : "inactive"
            },
            "to":{
                "name_long":"Tonga (Tonga Islands)",
                "name_native":"faka Tonga",
                "status" : "inactive"
            },
            "tr":{
                "name_long":"Turkish",
                "name_native":"Türkçe",
                "status" : "inactive"
            },
            "ts":{
                "name_long":"Tsonga",
                "name_native":"Xitsonga",
                "status" : "inactive"
            },
            "tt":{
                "name_long":"Tatar",
                "name_native":"татарча, tatarça, تاتارچا‎",
                "status" : "inactive"
            },
            "tw":{
                "name_long":"Twi",
                "name_native":"Twi",
                "status" : "inactive"
            },
            "ty":{
                "name_long":"Tahitian",
                "name_native":"Reo Tahiti",
                "status" : "inactive"
            },
            "ug":{
                "name_long":"Uighur, Uyghur",
                "name_native":"Uyƣurqə, ئۇيغۇرچە‎",
                "status" : "inactive"
            },
            "uk":{
                "name_long":"Ukrainian",
                "name_native":"українська",
                "status" : "inactive"
            },
            "ur":{
                "name_long":"Urdu",
                "name_native":"اردو",
                "status" : "inactive"
            },
            "uz":{
                "name_long":"Uzbek",
                "name_native":"zbek, Ўзбек, أۇزبېك‎",
                "status" : "inactive"
            },
            "ve":{
                "name_long":"Venda",
                "name_native":"Tshivenḓa",
                "status" : "inactive"
            },
            "vi":{
                "name_long":"Vietnamese",
                "name_native":"Tiếng Việt",
                "status" : "inactive"
            },
            "vo":{
                "name_long":"Volapük",
                "name_native":"Volapük",
                "status" : "inactive"
            },
            "wa":{
                "name_long":"Walloon",
                "name_native":"Walon",
                "status" : "inactive"
            },
            "cy":{
                "name_long":"Welsh",
                "name_native":"Cymraeg",
                "status" : "inactive"
            },
            "wo":{
                "name_long":"Wolof",
                "name_native":"Wollof",
                "status" : "inactive"
            },
            "fy":{
                "name_long":"Western Frisian",
                "name_native":"Frysk",
                "status" : "inactive"
            },
            "xh":{
                "name_long":"Xhosa",
                "name_native":"isiXhosa",
                "status" : "inactive"
            },
            "yi":{
                "name_long":"Yiddish",
                "name_native":"ייִדיש",
                "status" : "inactive"
            },
            "yo":{
                "name_long":"Yoruba",
                "name_native":"Yorùbá",
                "status" : "inactive"
            },
            "za":{
                "name_long":"Zhuang, Chuang",
                "name_native":"Saɯ cueŋƅ, Saw cuengh",
                "status" : "inactive"
            }
        }';

        $isoLangsDecode = json_decode($isoLangs);

        foreach ($isoLangsDecode as $code => $value) {
            $language = new Language();
            $language->name = $code;
            $language->name_long = $value->name_long;
            $language->name_native = $value->name_native;
            $language->status = $value->status;
            $language->save();
        }

        $this->command->info('countries table seeded.');

    }
}
