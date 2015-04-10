<?php
/**
 * Seeder for Personal Interest
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class PersonalInterestTableSeeder extends Seeder
{
    public function run()
    {
        $sources = <<<'COUNTRY'
Acting
Calligraphy
Candle making
Computer programming
Cooking
Coloring
Cosplaying
Couponing
Creative writing
Crocheting
Cryptography
Dance
Digital arts
Drama
Drawing
Electronics
Embroidery
Flower
Language
Gaming
Gambling
Genealogy
Homebrewing
Ice skating
Jewelry
Juggling
Knitting
Kabaddi
Lacemaking
Lapidary
Leather crafting
Lego Building
Machining
Macrame
Magic
Model Building
Music
Origami
Painting
Playing musical instruments
Pottery
Puzzles
Quilting
Reading
Scrapbooking
Sculpting
Sewing
Singing
Sketching
Soapmaking
Sports
Stand-Up Comedy
Taxidermy
Video gaming
Watching movies
Web surfing
Wood carving
Woodworking
Worldbuilding
Writing
Yoga
Yo-yoing
Indoor
Outdoor
Games
Fashion
Hiking
Climbing
Shopping
Foods
Billiard
Golf
Parasailing
Traveling
Fishing
Technology
Computer
Astronomy
Astrology
Science
COUNTRY;

        $interests = explode("\n", $sources);
        sort($interests, SORT_STRING);

        $this->command->info('Seeding personal_interests table...');

        try {
            DB::table('personal_interests')->truncate();
        } catch (Illuminate\Database\QueryException $e) {
        }

        foreach ($interests as $interest) {
            $interest = trim($interest);
            $interestLower = str_replace([' ', '-'], ['_', '_'], $interest);
            $interestLower = strtolower($interestLower);

            $record = [
                'personal_interest_name'    => $interestLower,
                'personal_interest_value'   => $interest,
                'status'                    => 'active'
            ];
            PersonalInterest::unguard();
            PersonalInterest::create($record);
            $this->command->info(sprintf('    Create record for %s.', $interest));
        }
        $this->command->info('personal_interests table seeded.');
    }
}
