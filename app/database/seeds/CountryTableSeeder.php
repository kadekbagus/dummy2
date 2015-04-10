<?php
/**
 * Seeder for Countries
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class CountryTableSeeder extends Seeder
{
    public function run()
    {
        $sources = <<<COUNTRY
AF;Afghanistan
AL;Albania
DZ;Algeria
AS;American Samoa
AD;Andorra
AO;Angola
AI;Anguilla
AQ;Antarctica
AG;Antigua and Barbuda
AR;Argentina
AM;Armenia
AW;Aruba
AU;Australia
AT;Austria
AZ;Azerbaijan
BS;Bahamas
BH;Bahrain
BD;Bangladesh
BB;Barbados
BY;Belarus
BE;Belgium
BZ;Belize
BJ;Benin
BM;Bermuda
BT;Bhutan
BO;Bolivia
BA;Bosnia and Herzegovina
BW;Botswana
BV;Bouvet Island
BR;Brazil
IO;British Indian Ocean Territory
BN;Brunei
BG;Bulgaria
BF;Burkina Faso
BI;Burundi
KH;Cambodia
CM;Cameroon
CA;Canada
CV;Cape Verde
KY;Cayman Islands
CF;Central African Republic
TD;Chad
CL;Chile
CN;China
CX;Christmas Island
CC;Cocos (Keeling) Islands
CO;Colombia
KM;Comoros
CG;Congo
CD;Congo, The Democratic Republic of the
CK;Cook Islands
CR;Costa Rica
CI;Côte d’Ivoire
HR;Croatia
CU;Cuba
CY;Cyprus
CZ;Czech Republic
DK;Denmark
DJ;Djibouti
DM;Dominica
DO;Dominican Republic
TP;East Timor
EC;Ecuador
EG;Egypt
SV;El Salvador
GQ;Equatorial Guinea
ER;Eritrea
EE;Estonia
ET;Ethiopia
FK;Falkland Islands
FO;Faroe Islands
FJ;Fiji Islands
FI;Finland
FR;France
GF;French Guiana
PF;French Polynesia
TF;French Southern territories
GA;Gabon
GM;Gambia
GE;Georgia
DE;Germany
GH;Ghana
GI;Gibraltar
GR;Greece
GL;Greenland
GD;Grenada
GP;Guadeloupe
GU;Guam
GT;Guatemala
GN;Guinea
GW;Guinea-Bissau
GY;Guyana
HT;Haiti
HM;Heard Island and McDonald Islands
VA;Holy See (Vatican City State)
HN;Honduras
HK;Hong Kong
HU;Hungary
IS;Iceland
IN;India
ID;Indonesia
IR;Iran
IQ;Iraq
IE;Ireland
IL;Israel
IT;Italy
JM;Jamaica
JP;Japan
JO;Jordan
KZ;Kazakstan
KE;Kenya
KI;Kiribati
KW;Kuwait
KG;Kyrgyzstan
LA;Laos
LV;Latvia
LB;Lebanon
LS;Lesotho
LR;Liberia
LY;Libyan Arab Jamahiriya
LI;Liechtenstein
LT;Lithuania
LU;Luxembourg
MO;Macao
MK;Macedonia
MG;Madagascar
MW;Malawi
MY;Malaysia
MV;Maldives
ML;Mali
MT;Malta
MH;Marshall Islands
MQ;Martinique
MR;Mauritania
MU;Mauritius
YT;Mayotte
MX;Mexico
FM;Micronesia, Federated States of
MD;Moldova
MC;Monaco
MN;Mongolia
MS;Montserrat
MA;Morocco
MZ;Mozambique
MM;Myanmar
NA;Namibia
NR;Nauru
NP;Nepal
NL;Netherlands
AN;Netherlands Antilles
NC;New Caledonia
NZ;New Zealand
NI;Nicaragua
NE;Niger
NG;Nigeria
NU;Niue
NF;Norfolk Island
KP;North Korea
MP;Northern Mariana Islands
NO;Norway
OM;Oman
PK;Pakistan
PW;Palau
PS;Palestine
PA;Panama
PG;Papua New Guinea
PY;Paraguay
PE;Peru
PH;Philippines
PN;Pitcairn
PL;Poland
PT;Portugal
PR;Puerto Rico
QA;Qatar
RE;Réunion
RO;Romania
RU;Russian Federation
RW;Rwanda
SH;Saint Helena
KN;Saint Kitts and Nevis
LC;Saint Lucia
PM;Saint Pierre and Miquelon
VC;Saint Vincent and the Grenadines
WS;Samoa
SM;San Marino
ST;Sao Tome and Principe
SA;Saudi Arabia
SN;Senegal
SC;Seychelles
SL;Sierra Leone
SG;Singapore
SK;Slovakia
SI;Slovenia
SB;Solomon Islands
SO;Somalia
ZA;South Africa
GS;South Georgia and the South Sandwich Islands
KR;South Korea
ES;Spain
LK;Sri Lanka
SD;Sudan
SR;Suriname
SJ;Svalbard and Jan Mayen
SZ;Swaziland
SE;Sweden
CH;Switzerland
SY;Syria
TW;Taiwan
TJ;Tajikistan
TZ;Tanzania
TH;Thailand
TG;Togo
TK;Tokelau
TO;Tonga
TT;Trinidad and Tobago
TN;Tunisia
TR;Turkey
TM;Turkmenistan
TC;Turks and Caicos Islands
TV;Tuvalu
UG;Uganda
UA;Ukraine
AE;United Arab Emirates
GB;United Kingdom
US;United States
UM;United States Minor Outlying Islands
UY;Uruguay
UZ;Uzbekistan
VU;Vanuatu
VE;Venezuela
VN;Vietnam
VG;Virgin Islands, British
VI;Virgin Islands, U.S.
WF;Wallis and Futuna
EH;Western Sahara
YE;Yemen
YU;Yugoslavia
ZM;Zambia
ZW;Zimbabwe
COUNTRY;

        $countries = explode("\n", $sources);

        $this->command->info('Seeding countries table...');

        try {
            DB::table('countries')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }

        foreach ($countries as $country) {
            list($code, $name) = explode(';', $country);

            $record = [
                'name'  => $name,
                'code'  => $code
            ];
            Country::unguard();
            Country::create($record);
            $this->command->info(sprintf('    Create record for %s (%s).', $name, $code));
        }
        $this->command->info('countries table seeded.');
    }
}
