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

/** @var string $propertyTypeClassName */
/** @var bool $isCollection */
/** @var string $propertyName */
/** @var string $setter */

ob_start(); ?>
        if (isset($children-><?php echo $propertyName; ?>)) {
<?php if ($isCollection) : ?>
            foreach($children-><?php echo $propertyName; ?> as $child) {
                $type-><?php echo $setter; ?>(<?php echo $propertyTypeClassName; ?>::xmlUnserialize($child));
            }
<?php else : ?>
            $type-><?php echo $setter; ?>(<?php echo $propertyTypeClassName; ?>::xmlUnserialize($children-><?php echo $propertyName; ?>));
<?php endif; ?>
        }
<?php return ob_get_clean();
