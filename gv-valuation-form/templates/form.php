<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="gvval-wrapper" data-total-steps="11">
    <div class="gvval-progress">
        <div class="gvval-progress-top">
            <span class="gvval-progress-label">Krok <span class="gvval-current-step">1</span> z <span class="gvval-total-steps">11</span></span>
        </div>
        <div class="gvval-progress-bar"><span class="gvval-progress-fill"></span></div>
    </div>
    <form class="gvval-form" novalidate>
        <input type="hidden" name="session_id" class="gvval-session" value="" />
        <div class="gvval-steps" style="min-height:var(--gv-step-min-h,480px);">
            <section class="gvval-step gvval-active" data-step="0">
                <h3>Kontakt</h3>
                <div class="gvval-field">
                    <label for="contact_name">Meno a priezvisko<span class="gvval-required">*</span></label>
                    <input type="text" id="contact_name" name="contact_name" required />
                </div>
                <div class="gvval-field">
                    <label for="phone">Telefónne číslo<span class="gvval-required">*</span></label>
                    <input type="tel" id="phone" name="phone" required />
                    <small class="gvval-hint">Po zadaní platného čísla vás budeme vedieť kontaktovať.</small>
                </div>
            </section>
            <section class="gvval-step" data-step="1">
                <h3>Typ nehnuteľnosti</h3>
                <div class="gvval-card-options" role="radiogroup">
                    <label class="gvval-card">
                        <input type="radio" name="property_type" value="flat" />
                        <span>Byt</span>
                    </label>
                    <label class="gvval-card">
                        <input type="radio" name="property_type" value="house" />
                        <span>Dom</span>
                    </label>
                </div>
            </section>
            <section class="gvval-step" data-step="2">
                <h3>Adresa</h3>
                <div class="gvval-grid gvval-grid-three">
                    <div class="gvval-field gvval-field-wide">
                        <label for="address_street_number">Ulica a číslo domu</label>
                        <input type="text" id="address_street_number" name="address_street_number" autocomplete="address-line1" />
                    </div>
                    <div class="gvval-field">
                        <label for="address_city">Mesto/Obec</label>
                        <input type="text" id="address_city" name="address_city" autocomplete="address-level2" />
                    </div>
                    <div class="gvval-field">
                        <label for="address_zip">PSČ</label>
                        <input type="text" id="address_zip" name="address_zip" inputmode="numeric" autocomplete="postal-code" />
                    </div>
                </div>
                <input type="hidden" id="address_line" name="address_line" />
            </section>
            <section class="gvval-step" data-step="3">
                <h3>Výmera podlahy (m²)</h3>
                <div class="gvval-field gvval-range">
                    <label for="area_sqm_range">Vyberte výmeru<span class="gvval-required">*</span></label>
                    <div class="gvval-range-scale"><span>10 m²</span><span>200 m²</span></div>
                    <input type="range" id="area_sqm_range" name="area_sqm_range" min="10" max="200" step="1" value="10" />
                    <div class="gvval-range-output"><span class="gvval-area-output">10</span> m²</div>
                </div>
                <div class="gvval-field">
                    <label for="area_sqm_input">Alebo zadajte presne</label>
                    <input type="number" id="area_sqm_input" name="area_sqm_input" min="10" max="200" step="1" value="10" />
                </div>
            </section>
            <section class="gvval-step" data-step="4">
                <h3>Počet izieb</h3>
                <div class="gvval-pills" role="radiogroup">
                    <button type="button" class="gvval-pill" data-value="1">1</button>
                    <button type="button" class="gvval-pill" data-value="2">2</button>
                    <button type="button" class="gvval-pill" data-value="3">3</button>
                    <button type="button" class="gvval-pill" data-value="4">4</button>
                    <button type="button" class="gvval-pill" data-value="5">5</button>
                    <button type="button" class="gvval-pill" data-value="5_plus">5+</button>
                </div>
                <input type="hidden" name="rooms" id="rooms" />
            </section>
            <section class="gvval-step" data-step="5">
                <h3>Poschodie a výťah</h3>
                <div class="gvval-field">
                    <label for="floor">Poschodie</label>
                    <select id="floor" name="floor">
                        <option value="">Vyberte</option>
                        <option value="basement">Suterén</option>
                        <option value="ground">Prízemie</option>
                        <?php for ( $i = 1; $i <= 14; $i++ ) : ?>
                            <option value="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i . '. poschodie' ); ?></option>
                        <?php endfor; ?>
                        <option value="15_plus">15 a viac</option>
                    </select>
                </div>
                <div class="gvval-field gvval-toggle">
                    <label class="gvval-checkbox"><input type="checkbox" id="has_elevator" name="has_elevator" /> Výťah k dispozícii</label>
                </div>
            </section>
            <section class="gvval-step" data-step="6">
                <h3>Stav nehnuteľnosti</h3>
                <div class="gvval-card-stack" role="radiogroup">
                    <label class="gvval-card">
                        <input type="radio" name="condition" value="original" />
                        <span>Pôvodný stav</span>
                    </label>
                    <label class="gvval-card">
                        <input type="radio" name="condition" value="renovated" />
                        <span>Po rekonštrukcii</span>
                    </label>
                    <label class="gvval-card">
                        <input type="radio" name="condition" value="new_build" />
                        <span>Novostavba do 5 rokov</span>
                    </label>
                </div>
            </section>
            <section class="gvval-step" data-step="7">
                <h3>Príslušenstvo</h3>
                <div class="gvval-accessories-grid">
                    <div class="gvval-accessory">
                        <label class="gvval-checkbox"><input type="checkbox" id="has_balcony" name="has_balcony" /> Balkón</label>
                        <div class="gvval-nested gvval-nested-inline" data-toggle="has_balcony">
                            <label for="balcony_area">Plocha balkóna (m²)</label>
                            <input type="number" id="balcony_area" name="balcony_area" min="1" max="60" />
                        </div>
                    </div>
                    <div class="gvval-accessory">
                        <label class="gvval-checkbox"><input type="checkbox" id="has_terrace" name="has_terrace" /> Terasa</label>
                        <div class="gvval-nested gvval-nested-inline" data-toggle="has_terrace">
                            <label for="terrace_area">Plocha terasy (m²)</label>
                            <input type="number" id="terrace_area" name="terrace_area" min="2" max="200" />
                        </div>
                    </div>
                    <div class="gvval-accessory">
                        <label class="gvval-checkbox"><input type="checkbox" id="has_cellar" name="has_cellar" /> Pivnica</label>
                        <div class="gvval-nested gvval-nested-inline" data-toggle="has_cellar">
                            <label for="cellar_area">Plocha pivnice (m²)</label>
                            <input type="number" id="cellar_area" name="cellar_area" min="1" max="50" />
                        </div>
                    </div>
                </div>
                <div class="gvval-field gvval-field-compact">
                    <span class="gvval-field-title">Parkovanie</span>
                    <div class="gvval-pills gvval-parking">
                        <button type="button" class="gvval-pill" data-value="none">Bez parkovania</button>
                        <button type="button" class="gvval-pill" data-value="street">Ulica</button>
                        <button type="button" class="gvval-pill" data-value="reserved_outdoor">Rezervované státie</button>
                        <button type="button" class="gvval-pill" data-value="garage_private">Garáž (samostatná)</button>
                        <button type="button" class="gvval-pill" data-value="garage_inhouse">Garáž v dome</button>
                    </div>
                    <input type="hidden" name="parking" id="parking" value="none" />
                </div>
                <div class="gvval-nested gvval-nested-inline" data-toggle="parking">
                    <label for="parking_slots">Počet parkovacích miest</label>
                    <input type="number" id="parking_slots" name="parking_slots" min="1" max="3" />
                </div>
            </section>
            <section class="gvval-step" data-step="8">
                <h3>Rok výstavby / rekonštrukcie</h3>
                <div class="gvval-grid">
                    <div class="gvval-field">
                        <label for="year_built">Rok výstavby</label>
                        <select id="year_built" name="year_built"></select>
                    </div>
                    <div class="gvval-field">
                        <label class="gvval-checkbox"><input type="checkbox" id="has_renovation" name="has_renovation" /> Prebehla rekonštrukcia</label>
                    </div>
                    <div class="gvval-field gvval-nested" data-toggle="has_renovation">
                        <label for="year_renovated">Rok rekonštrukcie</label>
                        <select id="year_renovated" name="year_renovated"></select>
                    </div>
                </div>
            </section>
            <section class="gvval-step" data-step="9">
                <h3>Vykurovanie</h3>
                <div class="gvval-field">
                    <label for="heating">Typ vykurovania<span class="gvval-required">*</span></label>
                    <select id="heating" name="heating">
                        <option value="">Vyberte</option>
                        <option value="gas">Plyn</option>
                        <option value="district">Centrálne (mestská tepláreň)</option>
                        <option value="central_boiler">Ústredné (kotolňa v dome)</option>
                        <option value="electric">Elektrina</option>
                        <option value="heat_pump">Tepelné čerpadlo</option>
                        <option value="solid_fuel">Tuhé palivo</option>
                        <option value="other">Iné</option>
                    </select>
                </div>
                <div class="gvval-field gvval-nested" data-toggle="heating-other">
                    <label for="heating_other_note">Uveďte typ vykurovania</label>
                    <input type="text" id="heating_other_note" name="heating_other_note" />
                </div>
            </section>
            <section class="gvval-step" data-step="10">
                <h3>Fotky a nadštandard</h3>
                <div class="gvval-field gvval-upload">
                    <label>Fotky (voliteľné)</label>
                    <div class="gvval-upload-drop">
                        <input type="file" id="gvval_photos" name="gvval_photos[]" accept="image/jpeg,image/png,image/webp" multiple />
                        <span>Presuňte sem alebo vyberte (max 20 súborov, 10 MB každý)</span>
                    </div>
                    <div class="gvval-upload-list"></div>
                    <div class="gvval-upload-loader" hidden>
                        <div class="gvval-spinner"></div>
                    </div>
                </div>
                <div class="gvval-field">
                    <label for="extras_text">Nadštandard, poznámky</label>
                    <textarea id="extras_text" name="extras_text" rows="4"></textarea>
                </div>
            </section>
        </div>
        <div class="gvval-footer">
            <button type="button" class="gvval-back">Späť</button>
            <button type="button" class="gvval-next">Ďalej</button>
            <button type="submit" class="gvval-submit">Odoslať</button>
        </div>
    </form>
</div>
