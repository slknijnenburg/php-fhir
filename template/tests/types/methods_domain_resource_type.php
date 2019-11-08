<?php
/*
 * Copyright 2018-2019 Daniel Carbone (daniel.p.carbone@gmail.com)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/** @var \DCarbone\PHPFHIR\Config\VersionConfig $config */
/** @var \DCarbone\PHPFHIR\Definition\Types $types */
/** @var \DCarbone\PHPFHIR\Definition\Type $type */
/** @var \DCarbone\PHPFHIR\Definition\Type $bundleType */

// TODO: precompile list of ID's to test with?

ob_start(); ?>

    /**
     * @param string $format Either xml or json
     * @return string
     */
    protected function fetchResource($format)
    {
        $url = sprintf('<?php echo rtrim($config->getTestEndpoint(), '/') . '/' . $type->getFHIRName(); ?>/?_count=1&_format=%s&_pretty=true', $format);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10, // set low timeout to move things along...
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        $this->assertEmpty($err, sprintf('curl error seen: %s', $err));
        if (method_exists($this, 'assertIsString')) {
            $this->assertIsString($res);
        } else {
            $this->assertInternalType('string', $res);
        }
        return $res;
    }

    /**
     * @param string $json
     * @param bool $asArray
     * @return mixed
     */
    protected function decodeJSON($json, $asArray)
    {
        $this->assertJson($json);
        $decoded = json_decode($json, $asArray);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->fail(sprintf(
                'Error decoded JSON: %s; Raw: %s',
                function_exists('json_last_error_msg') ? json_last_error_msg() : ('Code: '.json_last_error()),
                $json
            ));
        }
        return $decoded;
    }

    public function testXML()
    {
        $xml = $this->fetchResource('xml');
        try {
            $bundle = <?php echo $bundleType->getClassName(); ?>::xmlUnserialize($xml);
        } catch(\Exception $e) {
            throw new AssertionFailedError(
                sprintf(
                    'Error building type "<?php echo $bundleType->getFHIRName(); ?>" from XML: %s; Returned XML: %s',
                    $e->getMessage(),
                    $xml
                ),
                $e->getCode(),
                $e
            );
        }
        $this->assertInstanceOf('<?php echo $bundleType->getFullyQualifiedClassName(true); ?>', $bundle);
        if (0 === count($bundle->getEntry())) {
            $this->markTestSkipped(sprintf(
                'Provided test endpoint "<?php echo $config->getTestEndpoint(); ?>" does not have any "<?php echo $type->getFHIRName(); ?>" entries to test against (returned xml: %s)',
                $xml
            ));
            return;
        }
        $this->assertCount(1, $bundle->getEntry());
        $entry = $bundle->getEntry()[0]->getResource();
        $xml2 = $entry->xmlSerialize()->saveXML();
        try {
            $type = <?php echo $type->getClassName(); ?>::xmlUnserialize($xml2);
        } catch (\Exception $e) {
            throw new AssertionFailedError(
                sprintf(
                    'Error building type "<?php echo $type->getFHIRName(); ?>" from XML: %s; XML: %s',
                    $e->getMessage(),
                    $xml2
                ),
                $e->getCode(),
                $e
            );
        }
        $this->assertInstanceOf('<?php echo $type->getFullyQualifiedClassName(true); ?>', $type);
        $this->assertEquals($entry->xmlSerialize()->saveXML(), $type->xmlSerialize()->saveXML());
    }

    public function testJSON()
    {
        $json = $this->fetchResource('json');
        $decoded = $this->decodeJSON($json, true);
        try {
            $bundle = new <?php echo $bundleType->getClassName(); ?>($decoded);
        } catch(\Exception $e) {
            throw new AssertionFailedError(
                sprintf(
                    'Error building type "<?php echo $bundleType->getFHIRName(); ?>" from JSON: %s; Returned JSON: %s',
                    $e->getMessage(),
                    $json
                ),
                $e->getCode(),
                $e
            );
        }
        if (0 === count($bundle->getEntry())) {
            $this->markTestSkipped(sprintf(
                'Provided test endpoint "<?php echo $config->getTestEndpoint(); ?>" does not have any <?php echo $type->getFHIRName(); ?>" entries to test against (returned json: %s)',
                $json
            ));
            return;
        }
        $this->assertCount(1, $bundle->getEntry());
        $entry = $bundle->getEntry()[0]->getResource();
        $json2 = json_encode($entry, JSON_PRETTY_PRINT);
        $decoded2 = $this->decodeJSON($json2, true);
        try {
            $type = new <?php echo $type->getClassName(); ?>($decoded2);
        } catch (\Exception $e) {
            throw new AssertionFailedError(
                sprintf(
                    'Error building type "<?php echo $type->getFHIRName(); ?>" from JSON: %s; JSON: %s',
                    $e->getMessage(),
                    $json2
                ),
                $e->getCode(),
                $e
            );
        }
        $this->assertEquals(json_encode($entry, JSON_PRETTY_PRINT), json_encode($type, JSON_PRETTY_PRINT));
    }
<?php
return ob_get_clean();